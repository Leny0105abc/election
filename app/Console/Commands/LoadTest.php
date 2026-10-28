<?php

namespace App\Console\Commands;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AnonymousBallot;
use App\Models\BallotSelection;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\ElectionService;
use App\Services\VoterEligibilityService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * End-to-end concurrent voting simulation over real HTTP.
 *
 * Creates a temporary FICTIONAL election + students, then in parallel:
 *   1. loads the login page, 2. signs every student in, 3. opens every ballot,
 *   4. submits every ballot TWICE at the same time (double-click / retry).
 * Finally it verifies: exactly one ballot per student, no lost ballots,
 * ballots = voters marked as voted. Cleans up unless --keep is given.
 *
 * Never run against production.
 */
class LoadTest extends Command
{
    protected $signature = 'schoolvote:load-test
        {--students=75 : Number of simulated students}
        {--url= : Base URL (defaults to APP_URL)}
        {--keep : Keep the test election and students afterwards}';

    protected $description = 'Simulate concurrent student voters over HTTP and verify ballot integrity (local/staging only)';

    private const PIN = '918273';

    public function handle(ElectionService $elections, VoterEligibilityService $eligibility): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run a load test in production.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('students'));
        $base = rtrim($this->option('url') ?: config('app.url'), '/');
        $tag = 'LOADTEST-'.now()->format('His');

        $this->info("Setting up {$count} fictional students ({$tag})…");
        [$election, $students, $ballots] = $this->setUp($tag, $count, $elections, $eligibility);

        $jars = [];
        foreach ($students as $s) {
            $jars[$s->id] = new CookieJar;
        }

        $client = fn () => Http::withOptions(['allow_redirects' => false, 'http_errors' => false, 'verify' => false])->timeout(60);
        $timings = [];

        try {
            // 1. Login page (CSRF tokens + session cookies).
            $tokens = [];
            $responses = $this->phase('Load login page', $timings, fn () => Http::pool(fn (Pool $pool) => $students->map(
                fn ($s) => $pool->as((string) $s->id)->withOptions(['cookies' => $jars[$s->id], 'verify' => false])->get($base.'/')
            )->all()));
            foreach ($responses as $id => $r) {
                $tokens[$id] = $this->csrf($r);
            }

            // 2. Sign in.
            $responses = $this->phase('Sign in', $timings, fn () => Http::pool(fn (Pool $pool) => $students->map(
                fn ($s) => $pool->as((string) $s->id)->withOptions(['cookies' => $jars[$s->id], 'allow_redirects' => false, 'verify' => false])
                    ->asForm()->post($base.'/login', ['_token' => $tokens[$s->id], 'identifier' => $s->student_number, 'pin' => self::PIN])
            )->all()));
            $loginOk = collect($responses)->filter(fn ($r) => $r instanceof Response && $r->status() === 302 && str_contains((string) $r->header('Location'), '/vote'))->count();
            $this->line("   signed in: {$loginOk}/{$count}");

            // 3. Open ballots (renders the Livewire ballot page).
            $responses = $this->phase('Open ballot', $timings, fn () => Http::pool(fn (Pool $pool) => $students->map(
                fn ($s) => $pool->as((string) $s->id)->withOptions(['cookies' => $jars[$s->id], 'verify' => false])->get("{$base}/vote/elections/{$election->id}/ballot")
            )->all()));
            foreach ($responses as $id => $r) {
                if ($r instanceof Response && $r->ok()) {
                    $tokens[$id] = $this->csrf($r) ?: $tokens[$id];
                }
            }
            $ballotOk = collect($responses)->filter(fn ($r) => $r instanceof Response && $r->ok())->count();
            $this->line("   ballots opened: {$ballotOk}/{$count}");

            // 4. Submit every ballot twice, simultaneously.
            $responses = $this->phase('Submit (x2 each)', $timings, fn () => Http::pool(function (Pool $pool) use ($students, $jars, $tokens, $ballots, $base, $election) {
                $requests = [];
                foreach ($students as $s) {
                    foreach ([1, 2] as $copy) {
                        $requests[] = $pool->as($s->id.'-'.$copy)
                            ->withOptions(['cookies' => $jars[$s->id], 'allow_redirects' => false, 'verify' => false])
                            ->asForm()
                            ->post("{$base}/vote/elections/{$election->id}/ballot", ['_token' => $tokens[$s->id], 'selections' => $ballots[$s->id]]);
                    }
                }

                return $requests;
            }));
            $toDone = collect($responses)->filter(fn ($r) => $r instanceof Response && str_contains((string) $r->header('Location'), '/submitted'))->count();
            $this->line("   submissions redirected to confirmation: {$toDone}/".($count * 2).' (both copies should land there)');
            $errors = collect($responses)->filter(fn ($r) => ! $r instanceof Response || $r->serverError())->count();

            // Verification straight from the database.
            $voted = ElectionVoter::where('election_id', $election->id)->where('has_voted', true)->count();
            $ballotCount = AnonymousBallot::where('election_id', $election->id)->count();
            $selections = BallotSelection::where('election_id', $election->id)->count();
            $expectedSelections = collect($ballots)->sum(fn ($b) => count($b, COUNT_RECURSIVE) - count($b));

            $this->newLine();
            $this->table(['Phase', 'Requests', 'Total (s)', 'Avg (ms)'], $timings);
            $this->table(['Check', 'Value', 'Expected', 'OK'], [
                ['Students marked as voted', $voted, $count, $voted === $count ? 'yes' : 'NO'],
                ['Anonymous ballots stored', $ballotCount, $count, $ballotCount === $count ? 'yes' : 'NO'],
                ['Ballot selections stored', $selections, $expectedSelections, $selections === $expectedSelections ? 'yes' : 'NO'],
                ['Server errors (5xx / network)', $errors, 0, $errors === 0 ? 'yes' : 'NO'],
            ]);

            $passed = $voted === $count && $ballotCount === $count && $selections === $expectedSelections;
            $passed ? $this->info('PASS: no duplicate or lost ballots.') : $this->error('FAIL: see the table above.');

            return $passed ? self::SUCCESS : self::FAILURE;
        } finally {
            if (! $this->option('keep')) {
                $this->cleanUp($election, $students);
                $this->line('Test data removed.');
            }
        }
    }

    /** @return array{0: Election, 1: Collection<int, Student>, 2: array<int, array<int, list<int>>>} */
    private function setUp(string $tag, int $count, ElectionService $elections, VoterEligibilityService $eligibility): array
    {
        $schoolYear = SchoolYear::ensureCurrent();
        $pinHash = Hash::make(self::PIN, ['rounds' => 4]);

        $students = DB::transaction(function () use ($tag, $count, $schoolYear, $pinHash) {
            $list = collect();
            for ($i = 1; $i <= $count + 4; $i++) {
                $student = Student::create([
                    'school_id' => $schoolYear->school_id,
                    'student_number' => sprintf('%s-%04d', $tag, $i),
                    'first_name' => 'Load',
                    'last_name' => 'Tester '.$i,
                    'is_active' => true,
                ]);
                $student->forceFill(['pin_hash' => $pinHash])->save();
                StudentEnrollment::create([
                    'student_id' => $student->id, 'school_year_id' => $schoolYear->id,
                    'grade_level' => 7 + ($i % 6), 'section' => 'LOAD', 'enrollment_status' => EnrollmentStatus::Enrolled, 'is_eligible' => true,
                ]);
                $list->push($student);
            }

            return $list;
        });

        $election = new Election([
            'school_year_id' => $schoolYear->id, 'name' => "{$tag} election", 'type' => 'other',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2), 'eligible_grades' => [7, 8, 9, 10, 11, 12],
        ]);
        $election->save();

        // Two positions, two candidates each (candidates are the 4 extra students).
        $candidateStudents = $students->splice($count);
        foreach (['Load President', 'Load Secretary'] as $order => $name) {
            $position = ElectionPosition::create(['election_id' => $election->id, 'name' => $name, 'display_order' => $order + 1, 'max_winners' => 1, 'allow_abstain' => true, 'is_active' => true]);
            foreach ($candidateStudents->splice(0, 2) as $cs) {
                Candidate::create([
                    'election_id' => $election->id, 'election_position_id' => $position->id, 'student_id' => $cs->id,
                    'grade_level' => 10, 'status' => CandidateStatus::Approved,
                ]);
            }
        }

        $election->status = ElectionStatus::Scheduled;
        $election->save();
        $eligibility->syncVoterRoll($election);
        $elections->open($election->fresh());

        // Random valid ballot per student.
        $positions = ElectionPosition::where('election_id', $election->id)->with('approvedCandidates')->get();
        $ballots = [];
        foreach ($students as $s) {
            foreach ($positions as $p) {
                $ballots[$s->id][$p->id] = [$p->approvedCandidates->random()->id];
            }
        }

        return [$election->fresh(), $students, $ballots];
    }

    private function phase(string $label, array &$timings, callable $run): array
    {
        $start = microtime(true);
        $responses = $run();
        $seconds = microtime(true) - $start;
        $timings[] = [$label, count($responses), number_format($seconds, 2), number_format($seconds / max(1, count($responses)) * 1000, 0)];
        $this->line(sprintf(' • %-18s %d requests in %.2fs', $label, count($responses), $seconds));

        return $responses;
    }

    private function csrf(mixed $response): ?string
    {
        if (! $response instanceof Response) {
            return null;
        }

        return preg_match('/name="csrf-token" content="([^"]+)"/', $response->body(), $m) ? $m[1] : null;
    }

    private function cleanUp(Election $election, $students): void
    {
        try {
            DB::transaction(function () use ($election) {
                $id = $election->id;
                BallotSelection::where('election_id', $id)->delete();
                AnonymousBallot::where('election_id', $id)->delete();
                ElectionVoter::where('election_id', $id)->delete();
                Candidate::where('election_id', $id)->delete();
                ElectionPosition::where('election_id', $id)->delete();
                Election::whereKey($id)->delete();
            });
            $tagPrefix = Str::before($election->name, ' election');
            Student::where('student_number', 'like', $tagPrefix.'-%')->each(function (Student $s) {
                $s->enrollments()->delete();
                $s->delete();
            });
        } catch (Throwable $e) {
            $this->warn('Cleanup incomplete: '.$e->getMessage());
        }
    }
}
