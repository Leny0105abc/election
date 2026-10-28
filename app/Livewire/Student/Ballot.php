<?php

namespace App\Livewire\Student;

use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Services\VoterEligibilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ballot navigation and review. Selections live only in this page's state
 * (never in browser storage). The final submission is a normal POST handled
 * by VoteController, which re-validates everything on the server.
 */
#[Layout('components.layouts.student')]
#[Title('Ballot')]
class Ballot extends Component
{
    #[Locked]
    public Election $election;

    /** @var array<int|string, list<int>> positionId => candidate ids */
    public array $selections = [];

    /** @var array<int|string, bool> positionId => abstained */
    public array $abstain = [];

    public int $step = 0;

    public bool $reviewing = false;

    public function mount(Election $election, VoterEligibilityService $eligibility)
    {
        $this->election = $election;
        $voter = $this->voter();

        if (! $voter) {
            return $this->redirectRoute('student.portal', navigate: false);
        }

        if ($voter->has_voted) {
            return $this->redirectRoute('student.ballot.done', $election);
        }

        if (! $election->isAcceptingVotes()) {
            session()->flash('error', 'Voting is not open for this election right now.');

            return $this->redirectRoute('student.portal');
        }

        if ($this->positions->isEmpty()) {
            session()->flash('error', 'There are no positions on your ballot. Please see the election committee.');

            return $this->redirectRoute('student.portal');
        }
    }

    private function voter(): ?ElectionVoter
    {
        return ElectionVoter::where('election_id', $this->election->id)
            ->where('student_id', Auth::guard('student')->id())
            ->first();
    }

    /** @return Collection<int, ElectionPosition> */
    #[Computed]
    public function positions(): Collection
    {
        $voter = $this->voter();

        return $voter
            ? app(VoterEligibilityService::class)->ballotPositions($this->election, $voter->grade_level)
            : new Collection;
    }

    private function position(int $positionId): ?ElectionPosition
    {
        return $this->positions->firstWhere('id', $positionId);
    }

    public function toggleCandidate(int $positionId, int $candidateId): void
    {
        $position = $this->position($positionId);

        if (! $position || ! $position->approvedCandidates->contains('id', $candidateId)) {
            return;
        }

        $current = array_map('intval', $this->selections[$positionId] ?? []);
        unset($this->abstain[$positionId]);

        if (in_array($candidateId, $current, true)) {
            $current = array_values(array_diff($current, [$candidateId]));
        } elseif ($position->max_winners === 1) {
            $current = [$candidateId];
        } elseif (count($current) >= $position->max_winners) {
            $this->dispatch('notify', message: "You can choose up to {$position->max_winners} for {$position->name}. Unselect one first.", type: 'warning');

            return;
        } else {
            $current[] = $candidateId;
        }

        $this->selections[$positionId] = $current;
    }

    public function toggleAbstain(int $positionId): void
    {
        $position = $this->position($positionId);

        if (! $position || ! $position->allow_abstain) {
            return;
        }

        if (! empty($this->abstain[$positionId])) {
            unset($this->abstain[$positionId]);
        } else {
            $this->abstain[$positionId] = true;
            $this->selections[$positionId] = [];
        }
    }

    public function isAnswered(int $positionId): bool
    {
        return ! empty($this->abstain[$positionId]) || ! empty($this->selections[$positionId]);
    }

    public function next(): void
    {
        $position = $this->positions[$this->step] ?? null;

        if ($position && ! $this->isAnswered($position->id)) {
            $this->dispatch('notify', message: $position->allow_abstain
                ? "Choose a candidate for {$position->name}, or select Abstain."
                : "Choose a candidate for {$position->name}.", type: 'warning');

            return;
        }

        if ($this->step >= $this->positions->count() - 1) {
            $this->review();

            return;
        }

        $this->step++;
    }

    public function previous(): void
    {
        $this->step = max(0, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        $this->reviewing = false;
        $this->step = max(0, min($step, $this->positions->count() - 1));
    }

    public function review(): void
    {
        foreach ($this->positions as $index => $position) {
            if (! $this->isAnswered($position->id)) {
                $this->step = $index;
                $this->reviewing = false;
                $this->dispatch('notify', message: "Please answer {$position->name} before reviewing.", type: 'warning');

                return;
            }
        }

        $this->reviewing = true;
    }

    public function render()
    {
        $answered = $this->positions->filter(fn ($p) => $this->isAnswered($p->id))->count();

        return view('livewire.student.ballot', [
            'positions' => $this->positions,
            'current' => $this->positions[$this->step] ?? null,
            'answered' => $answered,
            'student' => Auth::guard('student')->user(),
        ]);
    }
}
