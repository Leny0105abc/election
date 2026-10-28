<?php

namespace App\Livewire\Admin\Elections;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Services\AuditLogger;
use App\Services\ElectionService;
use App\Services\VoterEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Election overview')]
class Show extends Component
{
    use RunsGuardedActions;

    public Election $election;

    public bool $showExtend = false;

    public bool $showReopen = false;

    public string $newEndsAt = '';

    public string $reason = '';

    public function mount(Election $election): void
    {
        $this->authorize('view-monitoring');
        $this->election = $election;
    }

    public function schedule(ElectionService $service): void
    {
        $this->guarded('operate-elections', fn () => $service->schedule($this->election), 'Election scheduled and voter roll generated.');
    }

    public function unschedule(ElectionService $service): void
    {
        $this->guarded('operate-elections', fn () => $service->unschedule($this->election), 'Election moved back to Draft.');
    }

    public function open(ElectionService $service): void
    {
        $this->guarded('operate-elections', fn () => $service->open($this->election), 'Voting is now OPEN.');
    }

    public function close(ElectionService $service): void
    {
        $this->guarded('operate-elections', fn () => $service->close($this->election), 'Voting is now CLOSED.');
    }

    public function archive(ElectionService $service): void
    {
        $this->guarded('finalize-election', fn () => $service->archive($this->election), 'Election archived.');
    }

    public function syncRoll(VoterEligibilityService $eligibility): void
    {
        $this->guarded('operate-elections', function () use ($eligibility) {
            if (! in_array($this->election->status, [ElectionStatus::Draft, ElectionStatus::Scheduled, ElectionStatus::Open], true)) {
                throw new ElectionStateException('The voter roll is frozen once voting closes.');
            }
            $r = $eligibility->syncVoterRoll($this->election);
            AuditLogger::log('election.voter_roll_synced', $this->election, $r);
            $this->dispatch('notify', message: "Voter roll updated: {$r['added']} added, {$r['removed']} removed, {$r['total']} total.", type: 'success');
        });
    }

    public function openExtend(): void
    {
        $this->resetValidation();
        $this->reason = '';
        $this->newEndsAt = local_dt($this->election->ends_at?->addHour(), 'Y-m-d\TH:i');
        $this->showExtend = true;
    }

    public function extend(ElectionService $service): void
    {
        $this->validate(['newEndsAt' => 'required|date', 'reason' => 'required|string|min:5|max:500']);
        $this->guarded('operate-elections', function () use ($service) {
            $service->extend($this->election, CarbonImmutable::parse($this->newEndsAt, config('schoolvote.display_timezone'))->utc(), $this->reason);
            $this->showExtend = false;
        }, 'Voting period extended.');
    }

    public function openReopen(): void
    {
        $this->resetValidation();
        $this->reason = '';
        $this->newEndsAt = local_dt(now()->addHour(), 'Y-m-d\TH:i');
        $this->showReopen = true;
    }

    public function reopen(ElectionService $service): void
    {
        $this->validate(['newEndsAt' => 'required|date', 'reason' => 'required|string|min:10|max:500']);
        $this->guarded('reopen-election', function () use ($service) {
            $service->reopen($this->election, CarbonImmutable::parse($this->newEndsAt, config('schoolvote.display_timezone'))->utc(), $this->reason);
            $this->showReopen = false;
        }, 'Election reopened. This action was recorded in the audit log.');
    }

    public function delete()
    {
        $this->authorize('manage-elections');

        if (! auth()->user()->can('delete', $this->election)) {
            $this->dispatch('notify', message: 'Only draft elections without ballots can be deleted.', type: 'error');

            return null;
        }

        DB::transaction(function () {
            AuditLogger::log('election.deleted', $this->election, ['name' => $this->election->name]);
            $this->election->candidates()->delete();
            $this->election->delete();
        });

        session()->flash('success', 'Draft election deleted.');

        return $this->redirectRoute('admin.elections.index', navigate: true);
    }

    public function render(ElectionService $service)
    {
        $this->election->refresh()->load('schoolYear');
        $voters = ElectionVoter::where('election_id', $this->election->id);
        $voted = (clone $voters)->where('has_voted', true)->count();
        $total = $voters->count();

        return view('livewire.admin.elections.show', [
            'problems' => $this->election->isEditable() ? $service->readinessProblems($this->election) : [],
            'stats' => [
                'positions' => $this->election->positions()->where('is_active', true)->count(),
                'candidates' => $this->election->candidates()->where('status', CandidateStatus::Approved)->count(),
                'pending' => $this->election->candidates()->where('status', CandidateStatus::Pending)->count(),
                'parties' => $this->election->partyLists()->count(),
                'voters' => $total,
                'voted' => $voted,
                'turnout' => $total ? round($voted / $total * 100, 1) : 0,
            ],
            'accepting' => $this->election->isAcceptingVotes(),
        ]);
    }
}
