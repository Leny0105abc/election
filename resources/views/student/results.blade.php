<x-layouts.student title="Official Results">
    <a href="{{ route('student.portal') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline">
        <x-lucide-arrow-left class="size-4" /> My elections
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Official results</h1>
    <p class="mb-6 text-sm text-slate-600">{{ $election->name }} · published {{ local_dt($election->results_published_at) }}</p>

    @foreach ($positions as $p)
        <section class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-100 px-4 py-3 font-bold text-slate-900">{{ $p['position']->name }}</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($p['rows'] as $row)
                    <li class="flex items-center gap-3 px-4 py-3">
                        <span class="w-6 text-center text-sm font-semibold text-slate-500">{{ $row->rank }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">{{ $row->candidate?->display_name }}</p>
                            <p class="text-xs text-slate-500">{{ $row->candidate?->partyLabel() }}</p>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row->is_winner ? 'bg-emerald-500' : 'bg-slate-300' }}" style="width: {{ min(100, (float) $row->percentage) }}%"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-slate-900">{{ number_format($row->votes) }}</p>
                            <p class="text-xs text-slate-500">{{ number_format((float) $row->percentage, 2) }}%</p>
                        </div>
                        @if ($row->is_winner)
                            <x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300"><x-lucide-trophy class="size-3" /> Winner</x-ui.badge>
                        @endif
                    </li>
                @endforeach
                <li class="flex items-center justify-between px-4 py-2 text-sm text-slate-500">
                    <span>Abstained</span><span>{{ number_format($p['abstentions']) }}</span>
                </li>
            </ul>
        </section>
    @endforeach
</x-layouts.student>
