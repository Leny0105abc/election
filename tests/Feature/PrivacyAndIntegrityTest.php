<?php

use App\Models\AnonymousBallot;
use App\Models\AuditLog;
use App\Models\BallotSelection;
use App\Models\Candidate;
use App\Models\ElectionVoter;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Services\AuditLogger;
use App\Services\VoteSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('keeps voter identity out of the anonymous ballot tables', function () {
    expect(Schema::getColumnListing('anonymous_ballots'))->toEqualCanonicalizing(['id', 'election_id'])
        ->and(Schema::getColumnListing('ballot_selections'))->toEqualCanonicalizing([
            'id', 'anonymous_ballot_id', 'election_id', 'election_position_id', 'candidate_id', 'is_abstain',
        ]);
});

it('uses random (non-sequential, non time-ordered) ballot ids', function () {
    $s = votingElection();
    foreach (range(1, 5) as $i) {
        $student = makeStudent(10);
        syncRoll($s['election']);
        app(VoteSubmissionService::class)->submit($s['election'], $student, grade10Ballot($s));
    }

    $ids = AnonymousBallot::pluck('id');
    expect($ids)->toHaveCount(5);
    foreach ($ids as $id) {
        expect(Str::isUuid($id))->toBeTrue()
            ->and($id[14])->toBe('4'); // UUID version 4 (random), not v7 (time-ordered)
    }
    foreach (BallotSelection::pluck('id') as $id) {
        expect($id[14])->toBe('4');
    }
});

it('rounds the participation time to the minute', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);
    app(VoteSubmissionService::class)->submit($s['election'], $student, grade10Ballot($s));

    $voter = ElectionVoter::where('student_id', $student->id)->first();
    expect($voter->voted_at->second)->toBe(0)
        ->and($voter->updated_at->second)->toBe(0);
});

it('never writes ballot choices to the audit log', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);
    app(VoteSubmissionService::class)->submit($s['election'], $student, grade10Ballot($s));

    $log = AuditLog::where('action', 'ballot.submitted')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties)->toBeNull()
        ->and(AuditLog::all()->toJson())->not->toContain('Alpha President');
});

it('strips sensitive keys from audit properties', function () {
    $log = AuditLogger::log('test.action', null, ['pin' => '123456', 'password' => 'x', 'selections' => [1], 'note' => 'ok']);
    expect($log->properties)->toBe(['note' => 'ok']);
});

it('makes audit log entries immutable', function () {
    $log = AuditLogger::log('test.action');
    expect(fn () => $log->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('enforces one participation record per student per election in the database', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    expect(fn () => ElectionVoter::create([
        'election_id' => $s['election']->id, 'student_id' => $student->id, 'grade_level' => 10, 'section' => 'X',
    ]))->toThrow(QueryException::class);
});

it('enforces one candidacy per student per election in the database', function () {
    $s = votingElection();
    $existing = $s['c']['p1'];

    expect(fn () => Candidate::create([
        'election_id' => $s['election']->id, 'election_position_id' => $s['councilor']->id,
        'student_id' => $existing->student_id, 'grade_level' => 11, 'status' => 'approved',
    ]))->toThrow(QueryException::class);
});

it('prevents the same candidate appearing twice on one ballot in the database', function () {
    $s = votingElection();
    $ballot = AnonymousBallot::create(['id' => (string) Str::uuid(), 'election_id' => $s['election']->id]);
    $row = ['anonymous_ballot_id' => $ballot->id, 'election_id' => $s['election']->id, 'election_position_id' => $s['president']->id, 'candidate_id' => $s['c']['p1']->id];

    BallotSelection::create($row + ['id' => (string) Str::uuid()]);
    expect(fn () => BallotSelection::create($row + ['id' => (string) Str::uuid()]))->toThrow(QueryException::class);
});

it('enforces one enrollment per student per school year', function () {
    $student = makeStudent(10);
    expect(fn () => StudentEnrollment::factory()->for($student)->create([
        'school_year_id' => $student->enrollments()->value('school_year_id'),
    ]))->toThrow(QueryException::class);
});

it('blocks deleting candidates that have received votes', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);
    app(VoteSubmissionService::class)->submit($s['election'], $student, grade10Ballot($s));

    expect(fn () => $s['c']['p1']->delete())->toThrow(QueryException::class);
});

it('reads cached school settings from a serializing cache store', function () {
    config(['cache.default' => 'database']);
    Cache::forget('schoolvote.school');

    school();             // cache miss: stores attributes
    $school = school();   // cache hit: rebuilt from cached attributes

    expect($school)->toBeInstanceOf(School::class)
        ->and($school->name)->toBe('Nancapian National High School');
    $this->get('/')->assertOk();
});

it('sends security headers and no-store for signed-in pages', function () {
    $student = makeStudent(10);
    $response = $this->actingAs($student, 'student')->withSession(['student_last_activity' => time()])
        ->get(route('student.portal'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});
