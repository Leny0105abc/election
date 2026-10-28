<div @if($data['polling'] ?? false) wire:poll.30s.visible @endif>
    <x-ui.page-header title="Dashboard" description="Live participation monitoring. Candidate vote totals stay hidden until voting closes.">
        <x-slot:actions>
            @if ($elections->isNotEmpty())
                <label for="election-picker" class="sr-only">Election</label>
                <select id="election-picker" wire:model.live="electionId" class="form-input min-w-64">
                    @foreach ($elections as $e)
                        <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->status->label() }})</option>
                    @endforeach
                </select>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $election)
        <x-ui.empty icon="vote" title="No elections yet" description="Create your first election to start monitoring participation.">
            @can('manage-elections')
                <x-ui.button :href="route('admin.elections.create')" icon="plus">Create election</x-ui.button>
            @endcan
        </x-ui.empty>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-500">{{ $election->type->label() }} · S.Y. {{ $election->schoolYear->name }}</p>
                <a href="{{ route('admin.elections.show', $election) }}" wire:navigate class="text-lg font-bold text-slate-900 hover:underline">{{ $election->name }}</a>
                <p class="text-sm text-slate-600">{{ local_dt($election->starts_at) }} — {{ local_dt($election->ends_at) }}</p>
            </div>
            <x-ui.badge :color="$election->status->badgeClasses()" class="text-sm">{{ $election->status->label() }}</x-ui.badge>
            @if ($data['polling'])
                <span class="flex items-center gap-1.5 text-xs text-emerald-700"><span class="size-2 animate-pulse rounded-full bg-emerald-500"></span> Live · refreshes every 30s</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <x-ui.stat label="Registered students" :value="number_format($data['registered'])" icon="users" tone="slate" hint="Enrolled in this school year" />
            <x-ui.stat label="Eligible voters" :value="number_format($data['eligible'])" icon="user-check" tone="brand" hint="On the voter roll" />
            <x-ui.stat label="Voted" :value="number_format($data['voted'])" icon="circle-check" tone="emerald" />
            <x-ui.stat label="Not yet voted" :value="number_format($data['notVoted'])" icon="clock" tone="amber" />
            <x-ui.stat label="Turnout" :value="$data['turnout'].'%'" icon="chart-column" tone="indigo" />
            <x-ui.stat label="Approved candidates" :value="number_format($data['candidates'])" icon="users-round" tone="slate" />
            <x-ui.stat label="Active positions" :value="number_format($data['positions'])" icon="list-ordered" tone="slate" />
            <x-ui.stat label="Status" :value="$election->status->label()" icon="vote" tone="brand" />
        </div>

        <div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="mb-2 flex justify-between text-sm font-medium"><span>Voting progress</span><span>{{ $data['voted'] }} / {{ $data['eligible'] }}</span></div>
            <div class="h-4 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $data['turnout'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Turnout">
                <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $data['turnout'] }}%"></div>
            </div>
        </div>

        @php
            $gradeLabels = $data['byGrade']->map(fn ($g) => 'Grade '.$g->grade_level)->values();
            $gradeVoted = $data['byGrade']->map(fn ($g) => (int) $g->voted)->values();
            $gradeNot = $data['byGrade']->map(fn ($g) => (int) $g->total - (int) $g->voted)->values();
            $gradeConfig = [
                'type' => 'bar',
                'data' => ['labels' => $gradeLabels, 'datasets' => [
                    ['label' => 'Voted', 'data' => $gradeVoted, 'backgroundColor' => '#10b981'],
                    ['label' => 'Not voted', 'data' => $gradeNot, 'backgroundColor' => '#e2e8f0'],
                ]],
                'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'animation' => false,
                    'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]]]],
            ];
            $pieConfig = [
                'type' => 'doughnut',
                'data' => ['labels' => ['Voted', 'Not voted'], 'datasets' => [['data' => [$data['voted'], $data['notVoted']], 'backgroundColor' => ['#10b981', '#e2e8f0']]]],
                'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'animation' => false, 'plugins' => ['legend' => ['position' => 'bottom']]],
            ];
            $timeConfig = [
                'type' => 'line',
                'data' => ['labels' => array_keys($data['timeline']), 'datasets' => [['label' => 'Students who voted (cumulative)', 'data' => array_values($data['timeline']), 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,0.15)', 'fill' => true, 'tension' => 0.25]]],
                'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'animation' => false, 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]],
            ];
        @endphp

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <x-ui.card title="Turnout by grade" class="lg:col-span-2">
                <div class="h-72" wire:key="grade-{{ md5(json_encode($gradeConfig)) }}" x-data="chart(@js($gradeConfig))">
                    <canvas x-ref="canvas" role="img" aria-label="Bar chart of voted and not voted students per grade"></canvas>
                </div>
            </x-ui.card>
            <x-ui.card title="Voted vs not voted">
                <div class="h-72" wire:key="pie-{{ md5(json_encode($pieConfig)) }}" x-data="chart(@js($pieConfig))">
                    <canvas x-ref="canvas" role="img" aria-label="Doughnut chart: {{ $data['voted'] }} voted, {{ $data['notVoted'] }} not voted"></canvas>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card title="Participation over time" description="Cumulative, in 15-minute intervals (Philippine time)" class="mt-4">
            @if ($data['timeline'])
                <div class="h-72" wire:key="time-{{ md5(json_encode($timeConfig)) }}" x-data="chart(@js($timeConfig))">
                    <canvas x-ref="canvas" role="img" aria-label="Line chart of cumulative participation"></canvas>
                </div>
            @else
                <p class="py-8 text-center text-sm text-slate-500">No ballots have been cast yet.</p>
            @endif
        </x-ui.card>
    @endif
</div>
