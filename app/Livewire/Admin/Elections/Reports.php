<?php

namespace App\Livewire\Admin\Elections;

use App\Models\Election;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Reports')]
class Reports extends Component
{
    public Election $election;

    public function mount(Election $election): void
    {
        $this->authorize('view-reports');
        $this->election = $election;
    }

    public function render(ReportService $reports)
    {
        $user = auth()->user();

        $items = collect(ReportService::REPORTS)->map(fn ($meta, $key) => $meta + [
            'key' => $key,
            'reason' => $reports->unavailableReason($this->election, $key, $user),
        ]);

        return view('livewire.admin.elections.reports', ['items' => $items]);
    }
}
