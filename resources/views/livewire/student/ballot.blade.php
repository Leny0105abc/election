<div>
    {{-- Election header --}}
    <div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-semibold tracking-wide text-brand uppercase">Official ballot</p>
        <h1 class="text-lg font-bold text-slate-900">{{ $election->name }}</h1>
        <p class="text-xs text-slate-500">Voting closes {{ local_dt($election->ends_at) }}</p>

        {{-- Progress --}}
        <div class="mt-3">
            <div class="mb-1 flex justify-between text-xs font-medium text-slate-600">
                <span>{{ $answered }} of {{ $positions->count() }} positions answered</span>
                <span>{{ $positions->count() ? round($answered / $positions->count() * 100) : 0 }}%</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $positions->count() }}" aria-valuenow="{{ $answered }}" aria-label="Ballot progress">
                <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $positions->count() ? $answered / $positions->count() * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900 ring-1 ring-rose-200">{{ session('error') }}</div>
    @endif

    @if (! $reviewing && $current)
        {{-- Position step --}}
        <section aria-labelledby="position-title">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-slate-500">Position {{ $step + 1 }} of {{ $positions->count() }}</p>
                    <h2 id="position-title" class="text-xl font-bold text-slate-900">{{ $current->name }}</h2>
                    <p class="text-sm text-slate-600">
                        @if ($current->max_winners > 1)
                            Choose up to <strong>{{ $current->max_winners }}</strong> candidates.
                        @else
                            Choose <strong>one</strong> candidate.
                        @endif
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($current->approvedCandidates as $candidate)
                    @php($selected = in_array($candidate->id, array_map('intval', $selections[$current->id] ?? []), true))
                    <div wire:key="cand-{{ $candidate->id }}" @class([
                        'overflow-hidden rounded-2xl bg-white shadow-sm ring-2 transition',
                        'ring-brand' => $selected,
                        'ring-slate-200' => ! $selected,
                    ])>
                        <button type="button" wire:click="toggleCandidate({{ $current->id }}, {{ $candidate->id }})"
                                class="flex w-full items-center gap-4 p-4 text-left focus:outline-none focus-visible:bg-slate-50"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                aria-label="{{ $selected ? 'Unselect' : 'Select' }} {{ $candidate->display_name }} for {{ $current->name }}">
                            @if ($candidate->photoUrl())
                                <img src="{{ $candidate->photoUrl() }}" alt="" class="size-16 shrink-0 rounded-xl object-cover">
                            @else
                                <span class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg font-bold text-slate-500">{{ $candidate->initials() }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-slate-900">{{ $candidate->display_name }}</span>
                                <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium text-white" style="background-color: {{ $candidate->partyList?->color ?? '#64748b' }}">
                                        {{ $candidate->partyLabel() }}
                                    </span>
                                    <span>Grade {{ $candidate->grade_level }}</span>
                                </span>
                            </span>
                            <span @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full border-2',
                                'border-brand bg-brand text-white' => $selected,
                                'border-slate-300 text-transparent' => ! $selected,
                            ]) aria-hidden="true">
                                <x-lucide-check class="size-5" />
                            </span>
                        </button>
                        @if ($candidate->platform)
                            <details class="border-t border-slate-100 px-4 py-2 text-sm">
                                <summary class="cursor-pointer py-1 font-medium text-brand">Read platform</summary>
                                <p class="pt-1 pb-2 whitespace-pre-line text-slate-700">{{ $candidate->platform }}</p>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($current->allow_abstain)
                @php($abstained = ! empty($abstain[$current->id]))
                <button type="button" wire:click="toggleAbstain({{ $current->id }})" aria-pressed="{{ $abstained ? 'true' : 'false' }}"
                        @class([
                            'mt-3 flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 text-sm font-semibold transition',
                            'border-slate-700 bg-slate-700 text-white' => $abstained,
                            'border-slate-300 bg-white text-slate-600 hover:border-slate-400' => ! $abstained,
                        ])>
                    @if ($abstained)<x-lucide-check class="size-5" />@endif
                    Abstain (no vote for {{ $current->name }})
                </button>
            @endif

            {{-- Navigation --}}
            <div class="sticky bottom-0 -mx-4 mt-6 border-t border-slate-200 bg-slate-100/95 px-4 py-3 backdrop-blur">
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" size="lg" wire:click="previous" icon="chevron-left" class="flex-1" :disabled="$step === 0">Previous</x-ui.button>
                    @if ($step < $positions->count() - 1)
                        <x-ui.button size="lg" wire:click="next" class="flex-1">Next <x-lucide-chevron-right class="size-4" /></x-ui.button>
                    @else
                        <x-ui.button size="lg" wire:click="review" icon="list-checks" class="flex-1">Review ballot</x-ui.button>
                    @endif
                </div>
                @if ($answered === $positions->count() && $step < $positions->count() - 1)
                    <button type="button" wire:click="review" class="mt-2 w-full text-center text-sm font-medium text-brand hover:underline">Skip to review</button>
                @endif
            </div>
        </section>
    @endif

    @if ($reviewing)
        {{-- Review & submit --}}
        <section aria-labelledby="review-title" x-data="{ confirming: false, submitting: false }">
            <h2 id="review-title" class="text-xl font-bold text-slate-900">Review your ballot</h2>
            <p class="mb-4 text-sm text-slate-600">Check your choices. You cannot change your vote after you submit.</p>

            <ol class="space-y-2">
                @foreach ($positions as $index => $position)
                    <li class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $position->name }}</p>
                            @if (! empty($abstain[$position->id]))
                                <p class="font-medium text-slate-500 italic">Abstain</p>
                            @else
                                @foreach ($position->approvedCandidates->whereIn('id', array_map('intval', $selections[$position->id] ?? [])) as $c)
                                    <p class="font-semibold text-slate-900">{{ $c->display_name }} <span class="text-xs font-normal text-slate-500">· {{ $c->partyLabel() }}</span></p>
                                @endforeach
                            @endif
                        </div>
                        <button type="button" wire:click="goTo({{ $index }})" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-3 text-sm font-medium text-brand hover:bg-brand/10">
                            <x-lucide-pencil class="size-4" /> Edit
                        </button>
                    </li>
                @endforeach
            </ol>

            <form method="POST" action="{{ route('student.ballot.submit', $election) }}" x-ref="ballotForm" @submit="submitting = true">
                @csrf
                @foreach ($positions as $position)
                    @if (! empty($abstain[$position->id]))
                        <input type="hidden" name="abstain[{{ $position->id }}]" value="1">
                    @else
                        @foreach ($selections[$position->id] ?? [] as $candidateId)
                            <input type="hidden" name="selections[{{ $position->id }}][]" value="{{ (int) $candidateId }}">
                        @endforeach
                    @endif
                @endforeach

                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <x-ui.button variant="secondary" size="lg" wire:click="goTo(0)" icon="pencil" class="sm:flex-1">Edit selections</x-ui.button>
                    <x-ui.button variant="success" size="lg" icon="vote" class="sm:flex-1" x-on:click="confirming = true">Submit final ballot</x-ui.button>
                </div>

                {{-- Confirmation dialog --}}
                <div x-cloak x-show="confirming" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-0 sm:items-center sm:p-4"
                     role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" @keydown.escape.window="if (!submitting) confirming = false">
                    <div class="w-full max-w-md rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl" @click.outside="if (!submitting) confirming = false">
                        <x-lucide-triangle-alert class="mb-3 size-10 text-amber-500" />
                        <h3 id="confirm-title" class="text-lg font-bold text-slate-900">Submit your final ballot?</h3>
                        <p class="mt-1 text-sm text-slate-600">Once submitted, your vote is final and cannot be changed.</p>
                        <div class="mt-5 flex flex-col gap-2 sm:flex-row-reverse">
                            <x-ui.button type="submit" variant="success" size="lg" class="sm:flex-1" x-bind:disabled="submitting">
                                <span x-show="!submitting">Yes, submit my vote</span>
                                <span x-cloak x-show="submitting">Submitting… please wait</span>
                            </x-ui.button>
                            <x-ui.button variant="secondary" size="lg" class="sm:flex-1" x-on:click="confirming = false" x-bind:disabled="submitting">Go back</x-ui.button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    @endif
</div>
