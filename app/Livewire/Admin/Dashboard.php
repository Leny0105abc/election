<?php

namespace App\Livewire\Admin;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\StudentEnrollment;
use App\Services\VoterEligibilityService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monitoring dashboard. Shows participation only — candidate vote totals
 * are never shown here (they appear on the Results page after voting closes).
 */
#[Layout('components.layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url(as: 'election')]
    public ?int $electionId = null;

    public function mount(): void
    {
        $this->authorize('view-monitoring');

        if (! $this->electionId) {
            $this->electionId = Election::query()
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'closed' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
                ->orderByDesc('starts_at')
                ->value('id');
        }
    }

    public function render(VoterEligibilityService $eligibility)
    {
        $elections = Election::orderByDesc('created_at')->get(['id', 'name', 'status']);
        $election = $this->electionId ? Election::with('schoolYear')->find($this->electionId) : null;

        $data = $election ? $this->stats($election, $eligibility) : null;

        return view('livewire.admin.dashboard', compact('elections', 'election', 'data'));
    }

    /** @return array<string, mixed> */
    private function stats(Election $election, VoterEligibilityService $eligibility): array
    {
        $registered = StudentEnrollment::where('school_year_id', $election->school_year_id)->count();
        $rollSize = ElectionVoter::where('election_id', $election->id)->count();
        $eligible = $rollSize > 0 ? $rollSize : $eligibility->eligibleEnrollments($election)->count();
        $voted = ElectionVoter::where('election_id', $election->id)->where('has_voted', true)->count();

        $byGrade = ElectionVoter::where('election_id', $election->id)
            ->select('grade_level', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN has_voted THEN 1 ELSE 0 END) as voted'))
            ->groupBy('grade_level')->orderBy('grade_level')->get();

        // Cumulative participation in 15-minute buckets (display timezone).
        $timeline = [];
        $running = 0;
        ElectionVoter::where('election_id', $election->id)->whereNotNull('voted_at')
            ->orderBy('voted_at')->pluck('voted_at')
            ->groupBy(function ($t) {
                $local = $t->setTimezone(config('schoolvote.display_timezone'));

                return $local->format('M j g:').str_pad((string) (intdiv((int) $local->format('i'), 15) * 15), 2, '0', STR_PAD_LEFT).$local->format(' A');
            })
            ->each(function ($group, $label) use (&$timeline, &$running) {
                $running += $group->count();
                $timeline[$label] = $running;
            });

        return [
            'registered' => $registered,
            'eligible' => $eligible,
            'voted' => $voted,
            'notVoted' => max(0, $eligible - $voted),
            'turnout' => $eligible > 0 ? round($voted / $eligible * 100, 1) : 0,
            'candidates' => $election->candidates()->where('status', CandidateStatus::Approved)->count(),
            'positions' => $election->positions()->where('is_active', true)->count(),
            'byGrade' => $byGrade,
            'timeline' => $timeline,
            'polling' => $election->status === ElectionStatus::Open,
        ];
    }
}
