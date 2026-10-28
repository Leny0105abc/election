<div>
    <x-ui.page-header title="Position catalogue" description="Default positions copied into new elections. Changes here never affect elections that already exist.">
        <x-slot:actions>
            <x-ui.button wire:click="create" icon="plus">New position</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead><tr><th>Order</th><th>Position</th><th>Winners</th><th>Voters</th><th>Candidates from</th><th>Abstain</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($positions as $p)
                    <tr wire:key="cp-{{ $p->id }}">
                        <td class="text-slate-500">{{ $p->display_order }}</td>
                        <td class="font-medium text-slate-900">{{ $p->name }}</td>
                        <td>{{ $p->max_winners }}</td>
                        <td class="text-xs">{{ $p->eligible_voter_grades ? 'Grade '.implode(', ', $p->eligible_voter_grades) : 'All grades' }}</td>
                        <td class="text-xs">{{ $p->eligible_candidate_grades ? 'Grade '.implode(', ', $p->eligible_candidate_grades) : 'All grades' }}</td>
                        <td>{{ $p->allow_abstain ? 'Yes' : 'No' }}</td>
                        <td>{!! $p->is_active ? '<span class="text-emerald-700">Active</span>' : '<span class="text-slate-400">Inactive</span>' !!}</td>
                        <td class="text-right"><button wire:click="edit({{ $p->id }})" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Edit {{ $p->name }}"><x-lucide-pencil class="size-4" /></button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit position' : 'New position'" max-width="max-w-2xl">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="name" label="Name" wire:model="name" class="sm:col-span-2" placeholder="e.g. Peace Officer" />
            <x-ui.textarea name="description" label="Description" wire:model="description" rows="2" class="sm:col-span-2" />
            <x-ui.input type="number" name="display_order" label="Default ballot order" wire:model="display_order" />
            <x-ui.input type="number" name="max_winners" label="Number of winners" wire:model="max_winners" min="1" />
            <x-ui.grade-checkboxes label="Who may vote" model="voter_grades" class="sm:col-span-2" help="None checked = all grades." />
            <x-ui.grade-checkboxes label="Who may run" model="candidate_grades" class="sm:col-span-2" help="None checked = all grades." />
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="allow_abstain" class="rounded border-slate-300 text-brand focus:ring-brand"> Allow abstain</label>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand focus:ring-brand"> Active (added to new elections)</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
