<?php

namespace App\Livewire\Admin\Elections;

use App\Models\Election;
use App\Models\ElectionVoter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Voter roll (participation only — never ballot choices).
 */
#[Layout('components.layouts.admin')]
#[Title('Voter roll')]
class Voters extends Component
{
    use WithPagination;

    public Election $election;

    #[Url]
    public string $search = '';

    #[Url]
    public string $grade = '';

    #[Url]
    public string $section = '';

    #[Url]
    public string $voted = '';

    public function mount(Election $election): void
    {
        $this->authorize('view-voter-reports');
        $this->election = $election;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'grade', 'section', 'voted'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $base = ElectionVoter::where('election_id', $this->election->id);

        $voters = (clone $base)
            ->with('student')
            ->when($this->grade !== '', fn ($q) => $q->where('grade_level', $this->grade))
            ->when($this->section !== '', fn ($q) => $q->where('section', $this->section))
            ->when($this->voted !== '', fn ($q) => $q->where('has_voted', $this->voted === '1'))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->search($this->search)))
            ->join('students', 'students.id', '=', 'election_voters.student_id')
            ->orderBy('election_voters.grade_level')->orderBy('election_voters.section')->orderBy('students.last_name')
            ->select('election_voters.*')
            ->paginate(25);

        return view('livewire.admin.elections.voters', [
            'voters' => $voters,
            'sections' => (clone $base)->when($this->grade !== '', fn ($q) => $q->where('grade_level', $this->grade))
                ->distinct()->orderBy('section')->pluck('section'),
            'total' => (clone $base)->count(),
            'votedCount' => (clone $base)->where('has_voted', true)->count(),
        ]);
    }
}
