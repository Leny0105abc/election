<div class="overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr><th class="w-14">Rank</th><th>Candidate</th><th>Party list</th><th class="text-right">Votes</th><th class="w-48">Share</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr @class(['bg-emerald-50/50' => $r->is_winner])>
                    <td class="font-semibold">{{ $r->rank }}</td>
                    <td class="font-medium text-slate-900">{{ $r->candidate?->display_name }}</td>
                    <td>{{ $r->candidate?->partyLabel() }}</td>
                    <td class="text-right font-semibold tabular-nums">{{ number_format($r->votes) }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $r->is_winner ? 'bg-emerald-500' : 'bg-slate-400' }}" style="width: {{ min(100, $r->percentage) }}%"></div>
                            </div>
                            <span class="w-14 text-right text-xs tabular-nums">{{ number_format($r->percentage, 2) }}%</span>
                        </div>
                    </td>
                    <td>
                        @if ($r->is_winner)
                            <x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300"><x-lucide-trophy class="size-3" /> {{ $r->status }}</x-ui.badge>
                        @elseif ($r->is_tied)
                            <x-ui.badge color="bg-rose-50 text-rose-700 ring-rose-300">{{ $r->status }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
