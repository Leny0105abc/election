<div>
    <x-election-nav :election="$election" />

    <x-ui.page-header title="Ballot positions" description="The order here is the order students see on the ballot.">
        <x-slot:actions>
            @can('manage-elections')
                @if ($editable)
                    <div class="flex gap-2">
                        <label for="catalog" class="sr-only">Add from catalogue</label>
                        <select id="catalog" wire:model="catalogPositionId" class="form-input w-56">
                            <option value="">Add from catalogue…</option>
                            @foreach ($catalog as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.button variant="secondary" wire:click="addFromCatalog" icon="plus">Add</x-ui.button>
                    </div>
                    <x-ui.button wire:click="create" icon="plus">Custom position</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($editable)
        <div class="mb-4 flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            <x-lucide-lock class="size-4" /> Positions are locked because the election is {{ $election->status->label() }}.
        </div>
    @endunless

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead>
                <tr>
                    <th>#</th><th>Position</th><th>Winners</th><th>Voters</th><th>Candidates from</th><th>Abstain</th><th>Candidates</th><th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $i => $p)
                    <tr wire:key="p-{{ $p->id }}" class="{{ $p->is_active ? '' : 'opacity-50' }}">
                        <td class="text-slate-500">{{ $i + 1 }}</td>
                        <td>
                            <p class="font-medium text-slate-900">{{ $p->name }}</p>
                            @unless ($p->is_active)<x-ui.badge>Inactive</x-ui.badge>@endunless
                        </td>
                        <td>{{ $p->max_winners }}</td>
                        <td class="text-xs">{{ $p->voterGradesLabel() }}</td>
                        <td class="text-xs">{{ $p->candidateGradesLabel() }}</td>
                        <td>{{ $p->allow_abstain ? 'Yes' : 'No' }}</td>
                        <td>{{ $p->approved_candidates_count }} <span class="text-xs text-slate-400">/ {{ $p->candidates_count }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            @can('manage-elections')
                                @if ($editable)
                                    <button wire:click="move({{ $p->id }}, -1)" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Move {{ $p->name }} up"><x-lucide-chevron-left class="size-4 rotate-90" /></button>
                                    <button wire:click="move({{ $p->id }}, 1)" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Move {{ $p->name }} down"><x-lucide-chevron-right class="size-4 rotate-90" /></button>
                                    <button wire:click="edit({{ $p->id }})" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Edit {{ $p->name }}"><x-lucide-pencil class="size-4" /></button>
                                    <button wire:click="delete({{ $p->id }})" wire:confirm="Remove {{ $p->name }} from this ballot?" class="rounded p-1.5 text-rose-500 hover:bg-rose-50" aria-label="Remove {{ $p->name }}"><x-lucide-trash-2 class="size-4" /></button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">No positions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit position' : 'New position'" max-width="max-w-2xl">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="name" label="Position name" wire:model="name" class="sm:col-span-2" />
            <x-ui.textarea name="description" label="Description" wire:model="description" class="sm:col-span-2" rows="2" />
            <x-ui.input type="number" name="display_order" label="Ballot order" wire:model="display_order" min="0" />
            <x-ui.input type="number" name="max_winners" label="Number of winners (max selections)" wire:model="max_winners" min="1" max="20" />
            <x-ui.grade-checkboxes label="Who may vote for this position" model="voter_grades" class="sm:col-span-2" help="Leave all unchecked for every grade in the election. Grade representatives: check only that grade." />
            <x-ui.grade-checkboxes label="Who may run for this position" model="candidate_grades" class="sm:col-span-2" help="Leave all unchecked to allow any grade." />
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="allow_abstain" class="rounded border-slate-300 text-brand focus:ring-brand"> Allow abstain</label>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand focus:ring-brand"> Active on ballot</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
