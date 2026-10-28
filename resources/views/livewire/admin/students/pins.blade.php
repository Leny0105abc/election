<div>
    <x-ui.page-header title="Generate student PINs" description="Creates a new PIN for each selected student and downloads printable slips. PINs are stored only as secure hashes and cannot be viewed again.">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.students.index')" wire:navigate>Back to students</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <div class="grid gap-4 md:grid-cols-4">
            <x-ui.select name="schoolYearId" label="School year" wire:model.live="schoolYearId">
                @foreach ($schoolYears as $sy)<option value="{{ $sy->id }}">{{ $sy->name }}</option>@endforeach
            </x-ui.select>
            <x-ui.select name="grade" label="Grade level" wire:model.live="grade">
                <option value="">All grades</option>
                @foreach (config('schoolvote.grade_levels') as $g)<option value="{{ $g }}">Grade {{ $g }}</option>@endforeach
            </x-ui.select>
            <x-ui.select name="section" label="Section" wire:model.live="section">
                <option value="">All sections</option>
                @foreach ($sections as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
            </x-ui.select>
            <x-ui.select name="mode" label="Which students" wire:model.live="mode">
                <option value="missing">Only students without a PIN</option>
                <option value="all">Everyone in this group (replace PINs)</option>
            </x-ui.select>
        </div>

        <div class="mt-5 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-700"><strong class="text-lg">{{ number_format($count) }}</strong> active, enrolled student(s) will receive a new PIN.</p>
            <x-ui.button icon="key-round" wire:click="generate" wire:loading.attr="disabled" :disabled="$count === 0"
                wire:confirm="{{ $mode === 'all' ? 'Replace the PINs of '.$count.' students? Their old PINs will stop working.' : 'Generate PINs for '.$count.' students?' }}">
                <span wire:loading.remove wire:target="generate">Generate &amp; download slips</span>
                <span wire:loading wire:target="generate">Generating… please wait</span>
            </x-ui.button>
        </div>

        <ul class="mt-4 list-disc space-y-1 pl-5 text-xs text-slate-600">
            <li>Generate one grade or section at a time on shared hosting.</li>
            <li>Distribute slips privately (e.g. by advisers in sealed envelopes). Shred unused slips.</li>
            <li>Lost PIN? Reset a single student's PIN from the Students page.</li>
        </ul>
    </x-ui.card>
</div>
