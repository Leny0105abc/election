<?php

namespace App\Services;

use App\Enums\ElectionStatus;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds report data in one neutral structure that the PDF, print and
 * Excel outputs all render. Reports never include individual ballot choices.
 *
 * Structure:
 *   title, summary (label => value), sections[] (heading, note, headings, rows, highlight[]), notes[]
 */
class ReportService
{
    /**
     * @var array<string, array{title: string, permission: string, requires: string|null, description: string}>
     */
    public const REPORTS = [
        'official-results' => ['title' => 'Official Election Results', 'permission' => 'view-reports', 'requires' => 'finalized', 'description' => 'Certified results per position from the finalized tally.'],
        'canvass' => ['title' => 'Election Canvassing Report', 'permission' => 'view-canvass', 'requires' => 'closed', 'description' => 'Detailed count per position with abstentions and integrity check.'],
        'winners' => ['title' => 'Winning Candidates', 'permission' => 'view-reports', 'requires' => 'finalized', 'description' => 'Proclaimed winners for every position.'],
        'candidate-votes' => ['title' => 'Candidate Vote Summary', 'permission' => 'view-canvass', 'requires' => 'closed', 'description' => 'Every candidate with votes, percentage and rank.'],
        'turnout' => ['title' => 'Voter Turnout', 'permission' => 'view-reports', 'requires' => null, 'description' => 'Registered vs voted totals and turnout percentage.'],
        'grade-participation' => ['title' => 'Grade-Level Participation', 'permission' => 'view-reports', 'requires' => null, 'description' => 'Turnout by grade level and section.'],
        'registered-voters' => ['title' => 'Registered Voters', 'permission' => 'view-voter-reports', 'requires' => null, 'description' => 'Official voter roll for this election.'],
        'voted' => ['title' => 'Students Who Voted', 'permission' => 'view-voter-reports', 'requires' => null, 'description' => 'Participation list (no ballot choices).'],
        'not-voted' => ['title' => 'Students Who Have Not Voted', 'permission' => 'view-voter-reports', 'requires' => null, 'description' => 'Voters who have not yet participated.'],
        'audit' => ['title' => 'Election Audit Report', 'permission' => 'view-audit', 'requires' => null, 'description' => 'Administrative actions recorded for this election.'],
    ];

    public function __construct(
        private readonly VoteCountingService $counter,
        private readonly ResultFinalizationService $finalization,
    ) {}

    /** Null when available, otherwise the reason it is not. */
    public function unavailableReason(Election $election, string $key, User $user): ?string
    {
        $meta = self::REPORTS[$key] ?? null;

        if (! $meta) {
            return 'Unknown report.';
        }

        if (! $user->hasPermission($meta['permission'])) {
            return 'Your role cannot access this report.';
        }

        return match ($meta['requires']) {
            'finalized' => in_array($election->status, [ElectionStatus::Finalized, ElectionStatus::Archived], true)
                ? null : 'Available after results are finalized.',
            'closed' => $election->status->allowsCanvass() ? null : 'Available after voting closes.',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function build(Election $election, string $key): array
    {
        $election->loadMissing('schoolYear');

        $report = match ($key) {
            'official-results' => $this->officialResults($election),
            'canvass' => $this->canvass($election),
            'winners' => $this->winners($election),
            'candidate-votes' => $this->candidateVotes($election),
            'turnout' => $this->turnout($election),
            'grade-participation' => $this->gradeParticipation($election),
            'registered-voters' => $this->voterList($election, null),
            'voted' => $this->voterList($election, true),
            'not-voted' => $this->voterList($election, false),
            'audit' => $this->audit($election),
        };

        return $report + [
            'key' => $key,
            'title' => self::REPORTS[$key]['title'],
            'summary' => [],
            'notes' => [],
            'landscape' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function officialResults(Election $election): array
    {
        $sections = $this->finalization->officialResults($election)->map(function ($p) {
            $rows = [];
            $highlight = [];
            foreach ($p['rows'] as $i => $r) {
                $rows[] = [
                    $r->rank,
                    $r->candidate?->display_name,
                    $r->candidate?->partyLabel(),
                    number_format($r->votes),
                    number_format((float) $r->percentage, 2).'%',
                    $r->is_winner ? ($r->is_tied ? 'WINNER (tie resolved)' : 'WINNER') : '',
                ];
                if ($r->is_winner) {
                    $highlight[] = $i;
                }
            }
            $rows[] = ['', 'Abstain / No vote', '', number_format($p['abstentions']), '', ''];

            return [
                'heading' => $p['position']->name,
                'note' => "Ballots for this position: {$p['ballots']} · Seats: {$p['position']->max_winners}",
                'headings' => ['Rank', 'Candidate', 'Party List', 'Votes', '%', 'Status'],
                'rows' => $rows,
                'highlight' => $highlight,
            ];
        })->all();

        return [
            'summary' => $this->turnoutSummary($election) + [
                'Finalized on' => local_dt($election->finalized_at),
            ],
            'sections' => $sections,
            'notes' => $this->tieNotes($election),
        ];
    }

    /** @return array<string, mixed> */
    private function canvass(Election $election): array
    {
        $integrity = $this->counter->integrity($election);
        $sections = $this->counter->tally($election)->map(function ($p) {
            $rows = [];
            $highlight = [];
            foreach ($p['rows'] as $i => $r) {
                $rows[] = [
                    $r['rank'], $r['candidate']->display_name, $r['candidate']->partyLabel(),
                    grade_label($r['candidate']->grade_level),
                    number_format($r['votes']), number_format($r['percentage'], 2).'%', $r['status'],
                ];
                if ($r['is_winner']) {
                    $highlight[] = $i;
                }
            }
            $rows[] = ['', 'Abstain / No vote', '', '', number_format($p['abstentions']), '', ''];

            return [
                'heading' => $p['position']->name,
                'note' => "Ballots: {$p['ballots']} · Seats: {$p['position']->max_winners} · Voters: {$p['position']->voterGradesLabel()}"
                    .($p['has_unresolved_tie'] ? ' · UNRESOLVED TIE — committee decision required' : ''),
                'headings' => ['Rank', 'Candidate', 'Party List', 'Grade', 'Votes', '%', 'Status'],
                'rows' => $rows,
                'highlight' => $highlight,
            ];
        })->all();

        return [
            'summary' => $this->turnoutSummary($election) + [
                'Anonymous ballots stored' => number_format($integrity['ballots']),
                'Integrity check' => $integrity['consistent'] ? 'PASSED (ballots = voters marked as voted)' : 'FAILED — investigate before finalizing',
            ],
            'sections' => $sections,
            'notes' => array_merge(
                ['Tie rule: '.($election->tie_resolution_rule ?: 'As decided by the Election Committee.')],
                $this->tieNotes($election),
            ),
            'landscape' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function winners(Election $election): array
    {
        $rows = [];
        foreach ($this->finalization->officialResults($election) as $p) {
            foreach ($p['rows']->where('is_winner', true) as $r) {
                $rows[] = [
                    $p['position']->name, $r->candidate?->display_name, $r->candidate?->partyLabel(),
                    grade_label($r->candidate?->grade_level), number_format($r->votes),
                ];
            }
        }

        return [
            'summary' => ['Finalized on' => local_dt($election->finalized_at)],
            'sections' => [[
                'heading' => null, 'note' => null,
                'headings' => ['Position', 'Winner', 'Party List', 'Grade', 'Votes'],
                'rows' => $rows, 'highlight' => [],
            ]],
            'notes' => $this->tieNotes($election),
        ];
    }

    /** @return array<string, mixed> */
    private function candidateVotes(Election $election): array
    {
        $rows = [];
        foreach ($this->counter->tally($election) as $p) {
            foreach ($p['rows'] as $r) {
                $rows[] = [
                    $p['position']->name, $r['candidate']->display_name, $r['candidate']->partyLabel(),
                    grade_label($r['candidate']->grade_level), number_format($r['votes']),
                    number_format($r['percentage'], 2).'%', $r['rank'], $r['status'],
                ];
            }
        }

        return [
            'sections' => [[
                'heading' => null, 'note' => null,
                'headings' => ['Position', 'Candidate', 'Party List', 'Grade', 'Votes', '%', 'Rank', 'Status'],
                'rows' => $rows, 'highlight' => [],
            ]],
            'landscape' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function turnout(Election $election): array
    {
        $byGrade = $this->gradeRows($election);

        return [
            'summary' => $this->turnoutSummary($election),
            'sections' => [[
                'heading' => 'Turnout by grade level', 'note' => null,
                'headings' => ['Grade Level', 'Registered', 'Voted', 'Not Voted', 'Turnout'],
                'rows' => $byGrade, 'highlight' => [],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function gradeParticipation(Election $election): array
    {
        $sections = ElectionVoter::where('election_id', $election->id)
            ->select('grade_level', 'section',
                DB::raw('COUNT(*) as registered'),
                DB::raw('SUM(CASE WHEN has_voted THEN 1 ELSE 0 END) as voted'))
            ->groupBy('grade_level', 'section')
            ->orderBy('grade_level')->orderBy('section')
            ->get()
            ->map(fn ($r) => [
                grade_label($r->grade_level), $r->section, (int) $r->registered, (int) $r->voted,
                (int) $r->registered - (int) $r->voted,
                $this->pct((int) $r->voted, (int) $r->registered),
            ])->all();

        return [
            'summary' => $this->turnoutSummary($election),
            'sections' => [
                ['heading' => 'By grade level', 'note' => null, 'headings' => ['Grade Level', 'Registered', 'Voted', 'Not Voted', 'Turnout'], 'rows' => $this->gradeRows($election), 'highlight' => []],
                ['heading' => 'By section', 'note' => null, 'headings' => ['Grade', 'Section', 'Registered', 'Voted', 'Not Voted', 'Turnout'], 'rows' => $sections, 'highlight' => []],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function voterList(Election $election, ?bool $voted): array
    {
        $rows = ElectionVoter::where('election_voters.election_id', $election->id)
            ->when($voted !== null, fn ($q) => $q->where('has_voted', $voted))
            ->join('students', 'students.id', '=', 'election_voters.student_id')
            ->orderBy('election_voters.grade_level')->orderBy('election_voters.section')
            ->orderBy('students.last_name')->orderBy('students.first_name')
            ->get(['election_voters.*', 'students.student_number', 'students.first_name', 'students.middle_name', 'students.last_name', 'students.suffix'])
            ->values()
            ->map(fn ($v, $i) => array_values(array_filter([
                $i + 1,
                $v->student_number,
                trim("{$v->last_name}, {$v->first_name} ".($v->middle_name ? mb_substr($v->middle_name, 0, 1).'.' : '').($v->suffix ? " {$v->suffix}" : '')),
                grade_label($v->grade_level),
                $v->section,
                $voted === null ? ($v->has_voted ? 'Voted' : 'Not yet voted') : false,
            ], fn ($x) => $x !== false)))
            ->all();

        $headings = ['#', 'Student ID', 'Name', 'Grade', 'Section'];
        if ($voted === null) {
            $headings[] = 'Status';
        }

        return [
            'summary' => $this->turnoutSummary($election) + ['Students listed' => number_format(count($rows))],
            'sections' => [['heading' => null, 'note' => null, 'headings' => $headings, 'rows' => $rows, 'highlight' => []]],
            'notes' => ['This list shows participation only. Ballot choices are secret and are never linked to students.'],
        ];
    }

    /** @return array<string, mixed> */
    private function audit(Election $election): array
    {
        $rows = AuditLog::where('election_id', $election->id)
            ->where('action', '!=', 'ballot.submitted')
            ->orderBy('created_at')->orderBy('id')
            ->limit(5000)
            ->get()
            ->map(fn (AuditLog $l) => [
                local_dt($l->created_at, 'Y-m-d H:i:s'),
                ucfirst((string) $l->actor_type).($l->actor_name ? ': '.$l->actor_name : ''),
                $l->action,
                $l->description ?? '',
                $l->ip_address ?? '',
            ])->all();

        $ballotEvents = AuditLog::where('election_id', $election->id)->where('action', 'ballot.submitted')->count();

        return [
            'summary' => [
                'Administrative events' => number_format(count($rows)),
                'Ballot submission events' => number_format($ballotEvents),
            ] + $this->integritySummary($election),
            'sections' => [['heading' => null, 'note' => null, 'headings' => ['Time', 'Actor', 'Action', 'Details', 'IP'], 'rows' => $rows, 'highlight' => []]],
            'notes' => ['Ballot submission events are counted but not listed individually to protect ballot secrecy.'],
            'landscape' => true,
        ];
    }

    /** @return array<string, string> */
    public function turnoutSummary(Election $election): array
    {
        $registered = ElectionVoter::where('election_id', $election->id)->count();
        $voted = ElectionVoter::where('election_id', $election->id)->where('has_voted', true)->count();

        return [
            'Registered voters' => number_format($registered),
            'Voted' => number_format($voted),
            'Not voted' => number_format($registered - $voted),
            'Turnout' => $this->pct($voted, $registered),
        ];
    }

    /** @return array<string, string> */
    private function integritySummary(Election $election): array
    {
        $i = $this->counter->integrity($election);

        return ['Integrity check' => $i['consistent'] ? 'Passed' : 'FAILED'];
    }

    /** @return list<list<mixed>> */
    private function gradeRows(Election $election): array
    {
        return ElectionVoter::where('election_id', $election->id)
            ->select('grade_level',
                DB::raw('COUNT(*) as registered'),
                DB::raw('SUM(CASE WHEN has_voted THEN 1 ELSE 0 END) as voted'))
            ->groupBy('grade_level')->orderBy('grade_level')
            ->get()
            ->map(fn ($r) => [
                grade_label($r->grade_level), (int) $r->registered, (int) $r->voted,
                (int) $r->registered - (int) $r->voted, $this->pct((int) $r->voted, (int) $r->registered),
            ])->all();
    }

    /** @return list<string> */
    private function tieNotes(Election $election): array
    {
        return $election->tieResolutions()->with('resolver')->get()->map(function ($t) {
            $position = $t->election_position_id ? ElectionPosition::find($t->election_position_id)?->name : '';

            return "Tie resolution ({$position}): {$t->method_and_notes} — recorded by ".($t->resolver?->name ?? 'committee').' on '.local_dt($t->created_at);
        })->all();
    }

    private function pct(int $part, int $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 2).'%' : '0.00%';
    }
}
