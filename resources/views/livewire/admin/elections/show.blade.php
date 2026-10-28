<div>
    <x-election-nav :election="$election" />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    @if ($election->bannerUrl())
                        <img src="{{ $election->bannerUrl() }}" alt="" class="h-28 w-full rounded-xl object-cover sm:w-48">
                    @endif
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl font-bold text-slate-900">{{ $election->name }}</h1>
                        <p class="text-sm text-slate-500">{{ $election->type->label() }} · S.Y. {{ $election->schoolYear->name }}</p>
                        @if ($election->description)<p class="mt-2 text-sm text-slate-700">{{ $election->description }}</p>@endif
                        <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
                            <div><dt class="inline text-slate-500">Opens:</dt> <dd class="inline font-medium">{{ local_dt($election->starts_at) ?: '—' }}</dd></div>
                            <div><dt class="inline text-slate-500">Closes:</dt> <dd class="inline font-medium">{{ local_dt($election->ends_at) ?: '—' }}</dd></div>
                            <div><dt class="inline text-slate-500">Grades:</dt> <dd class="inline font-medium">{{ implode(', ', $election->eligibleGrades()) }}</dd></div>
                            <div><dt class="inline text-slate-500">Results:</dt> <dd class="inline font-medium">{{ $election->results_published_at ? 'Published '.local_dt($election->results_published_at) : 'Not published' }}</dd></div>
                        </dl>
                    </div>
                    @can('update', $election)
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.elections.edit', $election)" icon="pencil" wire:navigate>Edit</x-ui.button>
                    @endcan
                </div>
            </x-ui.card>

            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <x-ui.stat label="Positions" :value="$stats['positions']" icon="list-ordered" tone="slate" />
                <x-ui.stat label="Candidates" :value="$stats['candidates']" icon="users-round" tone="slate" :hint="$stats['pending'] ? $stats['pending'].' pending approval' : null" />
                <x-ui.stat label="Voter roll" :value="number_format($stats['voters'])" icon="user-check" tone="brand" />
                <x-ui.stat label="Turnout" :value="$stats['turnout'].'%'" icon="chart-column" tone="emerald" :hint="$stats['voted'].' voted'" />
            </div>

            @if ($election->isEditable())
                <x-ui.card title="Readiness checklist">
                    @if ($problems)
                        <ul class="space-y-2 text-sm">
                            @foreach ($problems as $problem)
                                <li class="flex items-start gap-2 text-amber-800"><x-lucide-circle-alert class="mt-0.5 size-4 shrink-0" /> {{ $problem }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="flex items-center gap-2 text-sm text-emerald-700"><x-lucide-circle-check class="size-4" /> Ready. Schedule the election to lock the voter roll snapshot.</p>
                    @endif
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            <x-ui.card title="Election controls">
                <div class="space-y-3">
                    @if ($accepting)
                        <p class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"><span class="size-2 animate-pulse rounded-full bg-emerald-500"></span> Accepting ballots now</p>
                    @elseif ($election->status === \App\Enums\ElectionStatus::Open)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Open, but outside the voting window. Ballots are accepted only between the start and end times.</p>
                    @endif

                    @can('operate-elections')
                        @switch($election->status)
                            @case(\App\Enums\ElectionStatus::Draft)
                                <x-ui.button class="w-full" icon="calendar" wire:click="schedule" wire:confirm="Schedule this election? The voter roll will be generated from current enrollments.">Schedule election</x-ui.button>
                                @break
                            @case(\App\Enums\ElectionStatus::Scheduled)
                                <x-ui.button variant="success" class="w-full" icon="play" wire:click="open" wire:confirm="Open voting now? Students can vote once the start time is reached.">Open voting</x-ui.button>
                                <x-ui.button variant="secondary" class="w-full" wire:click="unschedule" wire:confirm="Move back to Draft to edit the schedule or ballot?">Back to draft</x-ui.button>
                                <p class="text-xs text-slate-500">With the scheduler running, voting also opens automatically at the start time.</p>
                                @break
                            @case(\App\Enums\ElectionStatus::Open)
                                <x-ui.button variant="danger" class="w-full" icon="square" wire:click="close" wire:confirm="Close voting now? Students will no longer be able to vote.">Close voting</x-ui.button>
                                <x-ui.button variant="secondary" class="w-full" icon="clock" wire:click="openExtend">Extend voting time</x-ui.button>
                                @break
                            @case(\App\Enums\ElectionStatus::Closed)
                                @can('view-canvass')
                                    <x-ui.button class="w-full" icon="trophy" :href="route('admin.elections.results', $election)" wire:navigate>Canvass &amp; finalize results</x-ui.button>
                                @endcan
                                @can('reopen-election')
                                    <x-ui.button variant="secondary" class="w-full" icon="refresh-cw" wire:click="openReopen">Reopen voting…</x-ui.button>
                                @endcan
                                @break
                            @case(\App\Enums\ElectionStatus::Finalized)
                                @can('finalize-election')
                                    <x-ui.button variant="secondary" class="w-full" icon="archive" wire:click="archive" wire:confirm="Archive this election? It stays available for reports.">Archive election</x-ui.button>
                                @endcan
                                @break
                        @endswitch

                        @if (in_array($election->status, [\App\Enums\ElectionStatus::Draft, \App\Enums\ElectionStatus::Scheduled, \App\Enums\ElectionStatus::Open], true))
                            <x-ui.button variant="ghost" class="w-full" icon="refresh-cw" wire:click="syncRoll">Refresh voter roll</x-ui.button>
                        @endif
                    @else
                        <p class="text-sm text-slate-500">Your role can monitor this election but not change its status.</p>
                    @endcan

                    @if ($election->status === \App\Enums\ElectionStatus::Draft)
                        @can('manage-elections')
                            <x-ui.button variant="ghost" class="w-full text-rose-600" icon="trash-2" wire:click="delete" wire:confirm="Delete this draft election permanently?">Delete draft</x-ui.button>
                        @endcan
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card title="Lifecycle">
                <ol class="space-y-2 text-sm">
                    @foreach (\App\Enums\ElectionStatus::cases() as $s)
                        <li class="flex items-center gap-2 {{ $s === $election->status ? 'font-semibold text-slate-900' : 'text-slate-500' }}">
                            <span class="size-2.5 rounded-full {{ $s === $election->status ? 'bg-brand' : 'bg-slate-300' }}"></span> {{ $s->label() }}
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal wire:model="showExtend" title="Extend voting time">
        <div class="space-y-4">
            <x-ui.input type="datetime-local" name="newEndsAt" label="New end time (Philippine time)" wire:model="newEndsAt" />
            <x-ui.textarea name="reason" label="Reason (recorded in the audit log)" wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="extend">Extend</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showReopen" title="Reopen voting">
        <div class="space-y-4">
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Reopening is an exceptional action. It requires a documented reason (e.g. committee resolution number) and is recorded in the audit log. Finalized elections cannot be reopened here.</p>
            <x-ui.input type="datetime-local" name="newEndsAt" label="New end time (Philippine time)" wire:model="newEndsAt" />
            <x-ui.textarea name="reason" label="Reason / committee resolution" wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button variant="danger" wire:click="reopen">Reopen voting</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
