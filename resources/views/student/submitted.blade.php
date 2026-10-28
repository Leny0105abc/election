<x-layouts.student title="Ballot Submitted">
    <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200 sm:p-10">
        <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <x-lucide-circle-check class="size-10" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $justSubmitted ? 'Your ballot has been recorded' : 'You have already voted' }}
        </h1>
        <p class="mt-2 text-slate-600">{{ $election->name }}</p>
        <p class="mx-auto mt-4 max-w-md text-sm text-slate-600">
            Thank you for voting! Your vote was saved securely and is secret. SchoolVote does not show or store
            your choices with your name, so we cannot display them here.
        </p>
        <p class="mt-4 text-xs text-slate-500">Participation recorded {{ local_dt($voter->voted_at, 'M j, Y') }}.</p>

        <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
            <form method="POST" action="{{ route('student.logout') }}">
                @csrf
                <x-ui.button type="submit" size="lg" icon="log-out" class="w-full sm:w-auto">Sign out</x-ui.button>
            </form>
            <x-ui.button variant="secondary" size="lg" :href="route('student.portal')" class="w-full sm:w-auto">Back to my elections</x-ui.button>
        </div>
        <p class="mt-6 text-xs text-slate-500">Using a shared device? Please sign out now.</p>
    </div>
</x-layouts.student>
