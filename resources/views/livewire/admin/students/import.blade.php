<div>
    <x-ui.page-header title="Import students" description="Upload an Excel or CSV file, review the preview, then confirm. Existing Student IDs are updated; new ones are added.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.students.template')" icon="download">Download template</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.students.index')" wire:navigate>Back to students</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.select name="schoolYearId" label="School year for enrollments" wire:model="schoolYearId">
                @foreach ($schoolYears as $sy)<option value="{{ $sy->id }}">{{ $sy->name }}{{ $sy->is_current ? ' (current)' : '' }}</option>@endforeach
            </x-ui.select>
            <div class="md:col-span-2">
                <label for="import-file" class="form-label">File (.xlsx or .csv, max 5 MB)</label>
                <input id="import-file" type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium">
                <div wire:loading wire:target="file" class="mt-1 text-xs text-slate-500">Uploading…</div>
                @error('file') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Columns: <code>{{ implode(', ', $headings) }}</code>. <code>enrollment_status</code>: enrolled, transferred, dropped or graduated. <code>eligible</code>: yes/no.</p>
        <div class="mt-4 flex gap-2">
            <x-ui.button wire:click="preview" icon="eye" wire:loading.attr="disabled" wire:target="preview,file">Preview import</x-ui.button>
            @if ($summary)<x-ui.button variant="ghost" wire:click="resetImport">Start over</x-ui.button>@endif
        </div>
    </x-ui.card>

    @if ($result)
        <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200">
            Imported: <strong>{{ $result['created'] }}</strong> new, <strong>{{ $result['updated'] }}</strong> updated, <strong>{{ $result['skipped'] }}</strong> skipped.
            Next: <a href="{{ route('admin.students.pins') }}" class="font-semibold underline" wire:navigate>generate PINs</a>.
        </div>
    @endif

    @if ($summary)
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-ui.stat label="Rows" :value="$summary['total']" icon="file-spreadsheet" tone="slate" />
            <x-ui.stat label="New" :value="$summary['new']" icon="plus" tone="emerald" />
            <x-ui.stat label="Updates" :value="$summary['update']" icon="refresh-cw" tone="brand" />
            <x-ui.stat label="Errors" :value="$summary['error']" icon="circle-alert" tone="rose" />
        </div>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="onlyErrors" class="rounded border-slate-300 text-brand focus:ring-brand"> Show only rows with errors</label>
            <x-ui.button variant="success" wire:click="commit" icon="check" :disabled="($summary['new'] + $summary['update']) === 0"
                wire:confirm="Import {{ $summary['new'] + $summary['update'] }} valid row(s)? Rows with errors will be skipped.">
                Confirm import ({{ $summary['new'] + $summary['update'] }} rows)
            </x-ui.button>
        </div>

        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="table-base">
                <thead><tr><th>Row</th><th>Action</th><th>Student ID</th><th>LRN</th><th>Name</th><th>Grade</th><th>Section</th><th>Status</th><th>Problems</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr wire:key="row-{{ $r['line'] }}" @class(['bg-rose-50/60' => $r['action'] === 'error'])>
                            <td class="text-slate-500">{{ $r['line'] }}</td>
                            <td>
                                @if ($r['action'] === 'new')<x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300">New</x-ui.badge>
                                @elseif ($r['action'] === 'update')<x-ui.badge color="bg-sky-50 text-sky-700 ring-sky-300">Update</x-ui.badge>
                                @else<x-ui.badge color="bg-rose-50 text-rose-700 ring-rose-300">Error</x-ui.badge>@endif
                            </td>
                            <td class="font-mono text-xs">{{ $r['data']['student_id'] }}</td>
                            <td class="font-mono text-xs">{{ $r['data']['lrn'] }}</td>
                            <td>{{ $r['data']['last_name'] }}, {{ $r['data']['first_name'] }} {{ $r['data']['middle_name'] }}</td>
                            <td>{{ $r['data']['grade_level'] }}</td>
                            <td>{{ $r['data']['section'] }}</td>
                            <td>{{ $r['data']['enrollment_status'] }}{{ $r['data']['eligible'] ? '' : ' · ineligible' }}</td>
                            <td class="text-xs text-rose-700">{{ implode(' ', $r['errors']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($rows) >= 500)<p class="mt-2 text-xs text-slate-500">Showing the first 500 rows.</p>@endif
    @endif
</div>
