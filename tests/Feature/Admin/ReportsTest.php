<?php

use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Services\ReportService;
use App\Services\ResultFinalizationService;
use App\Services\VoteSubmissionService;

function finalizedElection(): array
{
    $s = votingElection();
    // President: p1=2, p2=1. Councilor (2 seats): c1=2, c2=2, c3=1 — no deciding ties.
    foreach ([['p1', ['c1', 'c2']], ['p1', ['c1']], ['p2', ['c2', 'c3']]] as [$p, $cs]) {
        $student = makeStudent(10);
        syncRoll($s['election']);
        app(VoteSubmissionService::class)->submit($s['election']->fresh(), $student, [
            $s['president']->id => [$s['c'][$p]->id],
            $s['councilor']->id => array_map(fn ($c) => $s['c'][$c]->id, $cs),
        ]);
    }
    $s['election']->status = ElectionStatus::Closed;
    $s['election']->save();
    app(ResultFinalizationService::class)->finalize($s['election'], staff());

    return $s;
}

it('generates every report as PDF, Excel and printable HTML', function (string $report) {
    $s = finalizedElection();
    $admin = staff(Role::SuperAdmin);

    $this->actingAs($admin)->get(route('admin.reports.show', [$s['election'], $report, 'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($admin)->get(route('admin.reports.show', [$s['election'], $report, 'xlsx']))
        ->assertOk()->assertDownload();

    $this->actingAs($admin)->get(route('admin.reports.show', [$s['election'], $report, 'print']))
        ->assertOk()->assertSee(school()->name)->assertSee('Print this report');
})->with(array_keys(ReportService::REPORTS));

it('shows winners in the official results report', function () {
    $s = finalizedElection();
    $this->actingAs(staff())->get(route('admin.reports.show', [$s['election'], 'official-results', 'print']))
        ->assertSee('Alpha President')->assertSee('WINNER')->assertSee('Prepared by');
});

it('keeps participation lists free of ballot choices', function () {
    $s = finalizedElection();
    $this->actingAs(staff())->get(route('admin.reports.show', [$s['election'], 'voted', 'print']))
        ->assertOk()
        ->assertDontSee('Alpha President')
        ->assertDontSee('Charlie Councilor');
});

it('hides result reports until voting closes', function () {
    $s = votingElection();
    $this->actingAs(staff())->get(route('admin.reports.show', [$s['election'], 'canvass', 'pdf']))->assertForbidden();
    $this->actingAs(staff())->get(route('admin.reports.show', [$s['election'], 'official-results', 'pdf']))->assertForbidden();
    $this->actingAs(staff())->get(route('admin.reports.show', [$s['election'], 'turnout', 'pdf']))->assertOk();
});

it('restricts reports by role', function () {
    $s = finalizedElection();
    $this->actingAs(staff(Role::Committee))->get(route('admin.reports.show', [$s['election'], 'registered-voters', 'pdf']))->assertForbidden();
    $this->actingAs(staff(Role::ElectionAdmin))->get(route('admin.reports.show', [$s['election'], 'audit', 'pdf']))->assertForbidden();
    $this->actingAs(staff(Role::Committee))->get(route('admin.reports.show', [$s['election'], 'audit', 'pdf']))->assertOk();
});

it('rejects unknown report formats', function () {
    $s = finalizedElection();
    $this->actingAs(staff())->get('/admin/elections/'.$s['election']->id.'/reports/turnout/docx')->assertNotFound();
});
