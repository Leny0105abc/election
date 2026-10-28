<div>
    <x-election-nav :election="$election" />

    <x-ui.page-header title="Voter roll" description="Snapshot of eligible students for this election. Shows participation only — ballots are secret.">
        <p class="mt-2 text-sm text-slate-700"><strong>{{ number_format($votedCount) }}</strong> of <strong>{{ number_format($total) }}</strong> have voted.</p>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, ID or LRN" class="form-input" aria-label="Search voters">
        <select wire:model.live="grade" class="form-input" aria-label="Grade">
            <option value="">All grades</option>
            @foreach (config('schoolvote.grade_levels') as $g)<option value="{{ $g }}">Grade {{ $g }}</option>@endforeach
        </select>
        <select wire:model.live="section" class="form-input" aria-label="Section">
            <option value="">All sections</option>
            @foreach ($sections as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </select>
        <select wire:model.live="voted" class="form-input" aria-label="Voting status">
            <option value="">Voted and not voted</option>
            <option value="1">Voted</option>
            <option value="0">Not yet voted</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead><tr><th>Student ID</th><th>Name</th><th>Grade</th><th>Section</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($voters as $v)
                    <tr wire:key="v-{{ $v->id }}">
                        <td class="font-mono text-xs">{{ $v->student->student_number }}</td>
                        <td>{{ $v->student->sortable_name }}</td>
                        <td>{{ $v->grade_level }}</td>
                        <td>{{ $v->section }}</td>
                        <td>
                            @if ($v->has_voted)
                                <x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300">Voted</x-ui.badge>
                            @else
                                <x-ui.badge>Not yet</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No voters match. The roll is generated when the election is scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $voters->links() }}</div>
</div>
