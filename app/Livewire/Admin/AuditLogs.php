<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Election;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Audit log')]
class AuditLogs extends Component
{
    use WithPagination;

    #[Url]
    public string $action = '';

    #[Url]
    public string $actorType = '';

    #[Url]
    public string $electionId = '';

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        $this->authorize('view-audit');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->when($this->action !== '', fn ($q) => $q->where('action', 'like', $this->action.'%'))
            ->when($this->actorType !== '', fn ($q) => $q->where('actor_type', $this->actorType))
            ->when($this->electionId !== '', fn ($q) => $q->where('election_id', $this->electionId))
            ->when($this->date !== '', function ($q) {
                $tz = config('schoolvote.display_timezone');
                $start = CarbonImmutable::parse($this->date, $tz)->startOfDay()->utc();
                $q->whereBetween('created_at', [$start, $start->addDay()]);
            })
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.admin.audit-logs', [
            'logs' => $logs,
            'elections' => Election::orderByDesc('id')->get(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
