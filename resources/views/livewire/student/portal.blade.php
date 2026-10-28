<div>
    <h1 class="mb-1 text-2xl font-bold text-slate-900">Welcome!</h1>
    <p class="mb-6 text-sm text-slate-600">Here are the elections for your school. Times are Philippine time.</p>

    @forelse ($elections as $election)
        @php
            $voter = $voterRecords->get($election->id);
            $accepting = $election->isAcceptingVotes();
        @endphp
        <article class="mb-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            @if ($election->bannerUrl())
                <img src="{{ $election->bannerUrl() }}" alt="" class="h-32 w-full object-cover sm:h-40">
            @endif
            <div class="p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :color="$election->status->badgeClasses()">{{ $election->status->label() }}</x-ui.badge>
                    <span class="text-xs text-slate-500">{{ $election->type->label() }} · S.Y. {{ $election->schoolYear->name }}</span>
                </div>
                <h2 class="mt-2 text-lg font-bold text-slate-900">{{ $election->name }}</h2>
                @if ($election->description)
                    <p class="mt-1 text-sm text-slate-600">{{ $election->description }}</p>
                @endif

                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex items-center gap-2 text-slate-600">
                        <x-lucide-clock class="size-4 text-slate-400" />
                        <dt class="sr-only">Voting opens</dt>
                        <dd>Opens {{ local_dt($election->starts_at) }}</dd>
                    </div>
                    <div class="flex items-center gap-2 text-slate-600">
                        <x-lucide-clock class="size-4 text-slate-400" />
                        <dt class="sr-only">Voting closes</dt>
                        <dd>Closes {{ local_dt($election->ends_at) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl px-4 py-3 text-sm font-medium
                    {{ $voter?->has_voted ? 'bg-emerald-50 text-emerald-800' : ($voter ? 'bg-sky-50 text-sky-800' : 'bg-slate-50 text-slate-600') }}">
                    @if ($voter?->has_voted)
                        <span class="flex items-center gap-2"><x-lucide-circle-check class="size-5" /> You have voted. Thank you!</span>
                    @elseif ($voter)
                        <span class="flex items-center gap-2"><x-lucide-vote class="size-5" />
                            {{ $accepting ? 'You have not voted yet.' : 'You are on the voter list.' }}</span>
                    @else
                        <span class="flex items-center gap-2"><x-lucide-info class="size-5" /> You are not on the voter list for this election.</span>
                    @endif
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    @if ($voter && ! $voter->has_voted && $accepting)
                        <x-ui.button size="lg" :href="route('student.ballot', $election)" icon="vote" class="w-full sm:w-auto">Vote now</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" size="lg" :href="route('student.candidates', $election)" icon="users-round" class="w-full sm:w-auto">Candidates</x-ui.button>
                    @if ($election->resultsArePublished())
                        <x-ui.button variant="secondary" size="lg" :href="route('student.results', $election)" icon="trophy" class="w-full sm:w-auto">Results</x-ui.button>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <x-ui.empty icon="vote" title="No elections right now" description="Check back when the election committee announces the voting schedule." />
    @endforelse
</div>
