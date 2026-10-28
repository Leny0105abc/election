<div>
    <x-ui.page-header title="Students" description="Student voter records. Use fictional data for testing — never upload real records to a test system.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.students.import')" icon="upload" wire:navigate>Import</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.students.pins')" icon="key-round" wire:navigate>Generate PINs</x-ui.button>
            <x-ui.button wire:click="create" icon="plus">Add student</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, ID or LRN" class="form-input lg:col-span-2" aria-label="Search students">
        <select wire:model.live="schoolYearId" class="form-input" aria-label="School year">
            @foreach ($schoolYears as $sy)<option value="{{ $sy->id }}">S.Y. {{ $sy->name }}</option>@endforeach
        </select>
        <select wire:model.live="grade" class="form-input" aria-label="Grade">
            <option value="">All grades</option>
            @foreach (config('schoolvote.grade_levels') as $g)<option value="{{ $g }}">Grade {{ $g }}</option>@endforeach
        </select>
        <div class="grid grid-cols-2 gap-2">
            <select wire:model.live="section" class="form-input" aria-label="Section">
                <option value="">Section</option>
                @foreach ($sections as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
            </select>
            <select wire:model.live="eligibility" class="form-input" aria-label="Eligibility">
                <option value="">Eligibility</option>
                <option value="1">Eligible</option>
                <option value="0">Not eligible</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead><tr><th>Student ID</th><th>Name</th><th>Grade / Section</th><th>Enrollment</th><th>Eligible</th><th>PIN</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse ($students as $s)
                    @php($e = $s->enrollments->first())
                    <tr wire:key="s-{{ $s->id }}" class="{{ $s->is_active ? '' : 'opacity-60' }}">
                        <td>
                            <p class="font-mono text-xs">{{ $s->student_number }}</p>
                            @if ($s->lrn)<p class="font-mono text-[11px] text-slate-400">LRN {{ $s->lrn }}</p>@endif
                        </td>
                        <td>
                            <p class="font-medium text-slate-900">{{ $s->sortable_name }}</p>
                            @unless ($s->is_active)<x-ui.badge color="bg-zinc-100 text-zinc-600 ring-zinc-300">Account inactive</x-ui.badge>@endunless
                        </td>
                        <td>{{ $e ? 'Grade '.$e->grade_level.' · '.$e->section : '—' }}</td>
                        <td>{{ $e?->enrollment_status->label() ?? 'Not enrolled' }}</td>
                        <td>
                            @if ($e)
                                <button wire:click="toggleEligibility({{ $s->id }})" class="rounded-full" aria-label="Toggle eligibility for {{ $s->full_name }}">
                                    @if ($e->is_eligible)
                                        <x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300">Eligible</x-ui.badge>
                                    @else
                                        <x-ui.badge color="bg-rose-50 text-rose-700 ring-rose-300">Not eligible</x-ui.badge>
                                    @endif
                                </button>
                            @endif
                        </td>
                        <td class="text-xs">{{ $s->pin_hash ? 'Set '.local_dt($s->pin_generated_at, 'M j') : 'None' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button wire:click="resetPin({{ $s->id }})" wire:confirm="Generate a new PIN for {{ $s->full_name }}? The old PIN stops working." class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Reset PIN for {{ $s->full_name }}"><x-lucide-key-round class="size-4" /></button>
                            <button wire:click="edit({{ $s->id }})" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Edit {{ $s->full_name }}"><x-lucide-pencil class="size-4" /></button>
                            <button wire:click="delete({{ $s->id }})" wire:confirm="Delete {{ $s->full_name }}'s record permanently?" class="rounded p-1.5 text-rose-500 hover:bg-rose-50" aria-label="Delete {{ $s->full_name }}"><x-lucide-trash-2 class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">No students found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit student' : 'Add student'" max-width="max-w-2xl">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="student_number" label="Student ID" wire:model="student_number" />
            <x-ui.input name="lrn" label="LRN (optional, 12 digits)" wire:model="lrn" inputmode="numeric" />
            <x-ui.input name="first_name" label="First name" wire:model="first_name" />
            <x-ui.input name="middle_name" label="Middle name" wire:model="middle_name" />
            <x-ui.input name="last_name" label="Last name" wire:model="last_name" />
            <x-ui.input name="suffix" label="Suffix" wire:model="suffix" placeholder="Jr., III" />
            <div class="rounded-xl bg-slate-50 p-3 sm:col-span-2">
                <p class="mb-2 text-sm font-semibold text-slate-700">Enrollment for the selected school year</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.select name="grade_level" label="Grade level" wire:model="grade_level">
                        @foreach (config('schoolvote.grade_levels') as $g)<option value="{{ $g }}">Grade {{ $g }}</option>@endforeach
                    </x-ui.select>
                    <x-ui.input name="student_section" label="Section" wire:model="student_section" />
                    <x-ui.select name="enrollment_status" label="Enrollment status" wire:model="enrollment_status">
                        @foreach ($statuses as $st)<option value="{{ $st->value }}">{{ $st->label() }}</option>@endforeach
                    </x-ui.select>
                    <div class="flex flex-col justify-end gap-2 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_eligible" class="rounded border-slate-300 text-brand focus:ring-brand"> Eligible to vote</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand focus:ring-brand"> Account active</label>
                    </div>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check">Save student</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showPin" title="New PIN issued">
        <p class="text-sm text-slate-600">Give this PIN privately to <strong>{{ $issuedFor }}</strong>. It is shown only once and is not stored in readable form.</p>
        <p class="my-4 rounded-xl bg-slate-900 py-4 text-center font-mono text-4xl font-bold tracking-[0.4em] text-white select-all">{{ $issuedPin }}</p>
        <x-slot:footer>
            <x-ui.button wire:click="closePin">Done — I have recorded it</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
