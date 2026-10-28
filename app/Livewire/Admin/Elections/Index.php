<?php

namespace App\Livewire\Admin\Elections;

use App\Enums\ElectionStatus;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Elections')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('view-monitoring');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $elections = Election::query()
            ->with('schoolYear')
            ->withCount(['voters', 'voters as voted_count' => fn ($q) => $q->where('has_voted', true), 'positions', 'candidates'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.admin.elections.index', [
            'elections' => $elections,
            'statuses' => ElectionStatus::cases(),
        ]);
    }
}
