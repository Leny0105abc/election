<?php

namespace App\Livewire\Admin\Elections;

use App\Enums\ElectionStatus;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Election;
use App\Services\ResultFinalizationService;
use App\Services\VoteCountingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Canvassing (after voting closes), tie resolution, finalization and publication.
 * Vote totals are never shown while an election is Draft/Scheduled/Open.
 */
#[Layout('components.layouts.admin')]
#[Title('Results')]
class Results extends Component
{
    use RunsGuardedActions;

    public Election $election;

    public bool $showTie = false;

    public ?int $tiePositionId = null;

    /** @var list<int|string> */
    public array $tieWinners = [];

    public string $tieNotes = '';

    public function mount(Election $election): void
    {
        $this->authorize('view-canvass');
        $this->election = $election;
    }

    public function openTie(int $positionId): void
    {
        $this->authorize('finalize-election');
        $this->resetValidation();
        $this->tiePositionId = $positionId;
        $this->tieWinners = [];
        $this->tieNotes = '';
        $this->showTie = true;
    }

    public function resolveTie(ResultFinalizationService $service): void
    {
        $this->validate(['tieNotes' => 'required|string|min:10|max:1000', 'tieWinners' => 'required|array|min:1']);

        $this->guarded('finalize-election', function () use ($service) {
            $position = $this->election->positions()->findOrFail($this->tiePositionId);
            $service->resolveTie($this->election, $position, array_map('intval', $this->tieWinners), $this->tieNotes, auth()->user());
            $this->showTie = false;
        }, 'Tie resolution recorded.');
    }

    public function finalize(ResultFinalizationService $service): void
    {
        $this->guarded('finalize-election', fn () => $service->finalize($this->election, auth()->user()), 'Results finalized and locked.');
    }

    public function publish(ResultFinalizationService $service): void
    {
        $this->guarded('publish-results', fn () => $service->publish($this->election), 'Results published to students.');
    }

    public function unpublish(ResultFinalizationService $service): void
    {
        $this->guarded('publish-results', fn () => $service->unpublish($this->election), 'Results hidden from students.');
    }

    public function render(VoteCountingService $counter, ResultFinalizationService $finalization)
    {
        $this->election->refresh();
        $status = $this->election->status;

        $tally = null;
        $official = null;
        $integrity = null;

        if ($status === ElectionStatus::Closed) {
            $tally = $counter->tally($this->election);
            $integrity = $counter->integrity($this->election);
        } elseif (in_array($status, [ElectionStatus::Finalized, ElectionStatus::Archived], true)) {
            $official = $finalization->officialResults($this->election);
            $integrity = $counter->integrity($this->election);
        }

        $tiePosition = $tally && $this->tiePositionId ? $tally->firstWhere('position.id', $this->tiePositionId) : null;

        return view('livewire.admin.elections.results', compact('tally', 'official', 'integrity', 'tiePosition'));
    }
}
