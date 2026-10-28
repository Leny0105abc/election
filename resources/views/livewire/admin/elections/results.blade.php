<div>
    <x-election-nav :election="$election" />

    @if (! $election->status->allowsCanvass())
        <x-ui.empty icon="lock" title="Results are sealed"
            description="Candidate vote totals stay hidden while the election is {{ $election->status->label() }}. They become available to authorized staff after voting closes." />
    @else
        <x-ui.page-header :title="$official ? 'Official results' : 'Canvassing'"
            :description="$official ? 'Finalized '.local_dt($election->finalized_at).'. These totals are locked and cannot be edited.' : 'Live count from anonymous ballots. Review, resolve ties, then finalize.'">
            <x-slot:actions>
                @if ($official)
                    @can('publish-results')
                        @if ($election->results_published_at)
                            <x-ui.button variant="secondary" wire:click="unpublish" wire:confirm="Hide results from students?" icon="eye">Unpublish</x-ui.button>
                        @else
                            <x-ui.button variant="success" wire:click="publish" wire:confirm="Publish official results to all students?" icon="megaphone">Publish to students</x-ui.button>
                        @endif
                    @endcan
                @elseif ($tally)
                    @can('finalize-election')
                        <x-ui.button wire:click="finalize" icon="lock"
                            wire:confirm="Finalize and lock the official results? This cannot be undone from the app.">Finalize results</x-ui.button>
                    @endcan
                @endif
                <x-ui.button variant="secondary" :href="route('admin.elections.reports', $election)" icon="printer" wire:navigate>Reports</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($integrity)
            <div @class([
                'mb-4 flex items-start gap-2 rounded-xl px-4 py-3 text-sm ring-1',
                'bg-emerald-50 text-emerald-900 ring-emerald-200' => $integrity['consistent'],
                'bg-rose-50 text-rose-900 ring-rose-200' => ! $integrity['consistent'],
            ])>
                @if ($integrity['consistent'])
                    <x-lucide-shield-check class="mt-0.5 size-4 shrink-0" />
                    <span>Integrity check passed: {{ number_format($integrity['ballots']) }} anonymous ballots = {{ number_format($integrity['voted']) }} students marked as voted.</span>
                @else
                    <x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0" />
                    <span>Integrity check FAILED: {{ $integrity['ballots'] }} ballots vs {{ $integrity['voted'] }} voters. Do not finalize. Contact the system administrator.</span>
                @endif
            </div>
        @endif

        <div class="space-y-4">
            @if ($tally)
                @foreach ($tally as $p)
                    <x-ui.card wire:key="t-{{ $p['position']->id }}" :title="$p['position']->name" :description="'Ballots: '.$p['ballots'].' · Seats: '.$p['position']->max_winners.' · Abstained: '.$p['abstentions']" :padding="false">
                        @if ($p['has_unresolved_tie'])
                            <x-slot:actions>
                                <x-ui.badge color="bg-rose-50 text-rose-700 ring-rose-300">Unresolved tie</x-ui.badge>
                                @can('finalize-election')
                                    <x-ui.button size="sm" wire:click="openTie({{ $p['position']->id }})" icon="scale">Record tie resolution</x-ui.button>
                                @endcan
                            </x-slot:actions>
                        @endif
                        @include('livewire.admin.elections.partials.result-table', ['rows' => collect($p['rows'])->map(fn ($r) => (object) [
                            'rank' => $r['rank'], 'candidate' => $r['candidate'], 'votes' => $r['votes'], 'percentage' => $r['percentage'],
                            'is_winner' => $r['is_winner'], 'is_tied' => $r['is_tied'], 'status' => $r['status'],
                        ])])
                        @if ($p['tie_resolution'])
                            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-600"><strong>Tie resolution:</strong> {{ $p['tie_resolution']->method_and_notes }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            @elseif ($official)
                @foreach ($official as $p)
                    <x-ui.card wire:key="o-{{ $p['position']->id }}" :title="$p['position']->name" :description="'Ballots: '.$p['ballots'].' · Seats: '.$p['position']->max_winners.' · Abstained: '.$p['abstentions']" :padding="false">
                        @include('livewire.admin.elections.partials.result-table', ['rows' => $p['rows']->map(fn ($r) => (object) [
                            'rank' => $r->rank, 'candidate' => $r->candidate, 'votes' => $r->votes, 'percentage' => (float) $r->percentage,
                            'is_winner' => $r->is_winner, 'is_tied' => $r->is_tied, 'status' => $r->is_winner ? ($r->is_tied ? 'Winner (tie resolved)' : 'Winner') : '',
                        ])])
                    </x-ui.card>
                @endforeach
            @endif
        </div>

        <x-ui.modal wire:model="showTie" title="Record tie resolution">
            @if ($tiePosition)
                <p class="mb-3 text-sm text-slate-600">
                    {{ $tiePosition['position']->name }}: select the <strong>{{ $tiePosition['remaining_seats'] }}</strong> winner(s) chosen by the
                    committee's approved method ({{ $election->tie_resolution_rule ?: 'as decided by the committee' }}).
                </p>
                <fieldset class="space-y-2">
                    <legend class="form-label">Tied candidates</legend>
                    @foreach (collect($tiePosition['rows'])->whereIn('candidate.id', $tiePosition['tied_candidate_ids']) as $r)
                        <label class="flex items-center gap-2 rounded-lg p-2 text-sm ring-1 ring-slate-200">
                            <input type="checkbox" value="{{ $r['candidate']->id }}" wire:model="tieWinners" class="rounded border-slate-300 text-brand focus:ring-brand">
                            {{ $r['candidate']->display_name }} — {{ $r['votes'] }} votes
                        </label>
                    @endforeach
                    @error('tieWinners') <p class="form-error">{{ $message }}</p> @enderror
                </fieldset>
                <x-ui.textarea name="tieNotes" label="Method, witnesses, date and resolution number" wire:model="tieNotes" class="mt-4" rows="3" />
            @endif
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button wire:click="resolveTie" icon="check">Save resolution</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
