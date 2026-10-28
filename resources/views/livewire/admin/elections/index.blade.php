<div>
    <x-ui.page-header title="Elections" description="Create and run SSLG, classroom, club and other school elections.">
        <x-slot:actions>
            <label for="status-filter" class="sr-only">Filter by status</label>
            <select id="status-filter" wire:model.live="status" class="form-input w-40">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            @can('manage-elections')
                <x-ui.button :href="route('admin.elections.create')" icon="plus" wire:navigate>New election</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($elections->isEmpty())
        <x-ui.empty icon="vote" title="No elections found" description="Create an election to set up positions, candidates and the voter roll." />
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($elections as $election)
                <a href="{{ route('admin.elections.show', $election) }}" wire:navigate wire:key="e-{{ $election->id }}"
                   class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand">
                    <div class="flex items-center justify-between gap-2">
                        <x-ui.badge :color="$election->status->badgeClasses()">{{ $election->status->label() }}</x-ui.badge>
                        <span class="text-xs text-slate-500">S.Y. {{ $election->schoolYear->name }}</span>
                    </div>
                    <h2 class="mt-3 font-bold text-slate-900 group-hover:text-brand">{{ $election->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $election->type->label() }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ local_dt($election->starts_at) ?: 'Schedule not set' }}
                        @if ($election->ends_at) — {{ local_dt($election->ends_at) }} @endif</p>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-slate-50 p-2"><dt class="text-slate-500">Positions</dt><dd class="text-base font-bold">{{ $election->positions_count }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-2"><dt class="text-slate-500">Candidates</dt><dd class="text-base font-bold">{{ $election->candidates_count }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-2"><dt class="text-slate-500">Voted</dt><dd class="text-base font-bold">{{ $election->voted_count }}/{{ $election->voters_count }}</dd></div>
                    </dl>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $elections->links() }}</div>
    @endif
</div>
