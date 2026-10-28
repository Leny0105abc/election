<x-layouts.student title="Candidates">
    <a href="{{ route('student.portal') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline">
        <x-lucide-arrow-left class="size-4" /> My elections
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Candidates</h1>
    <p class="mb-6 text-sm text-slate-600">{{ $election->name }}</p>

    @forelse ($positions as $position)
        <section class="mb-6" aria-labelledby="pos-{{ $position->id }}">
            <h2 id="pos-{{ $position->id }}" class="mb-2 text-lg font-bold text-slate-900">{{ $position->name }}
                <span class="text-sm font-normal text-slate-500">· voters: {{ $position->voterGradesLabel() }}</span></h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($position->candidates as $candidate)
                    <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center gap-4">
                            @if ($candidate->photoUrl())
                                <img src="{{ $candidate->photoUrl() }}" alt="Photo of {{ $candidate->display_name }}" class="size-16 rounded-xl object-cover">
                            @else
                                <span class="flex size-16 items-center justify-center rounded-xl bg-slate-100 text-lg font-bold text-slate-500">{{ $candidate->initials() }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $candidate->display_name }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                                    <span class="rounded-full px-2 py-0.5 font-medium text-white" style="background-color: {{ $candidate->partyList?->color ?? '#64748b' }}">{{ $candidate->partyLabel() }}</span>
                                    Grade {{ $candidate->grade_level }}
                                </p>
                            </div>
                        </div>
                        @if ($candidate->platform)
                            <h3 class="mt-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Platform</h3>
                            <p class="text-sm whitespace-pre-line text-slate-700">{{ $candidate->platform }}</p>
                        @endif
                        @if ($candidate->biography)
                            <details class="mt-2 text-sm">
                                <summary class="cursor-pointer font-medium text-brand">About the candidate</summary>
                                <p class="mt-1 whitespace-pre-line text-slate-700">{{ $candidate->biography }}</p>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <x-ui.empty icon="users-round" title="No candidates yet" description="Approved candidates will appear here." />
    @endforelse
</x-layouts.student>
