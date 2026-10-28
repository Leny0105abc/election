<div>
    <x-election-nav :election="$election" />

    <x-ui.page-header title="Party lists" description="Party lists are shown on candidate cards only. They never pre-select or restrict a student's choices.">
        <x-slot:actions>
            @can('manage-elections')
                @if ($editable)<x-ui.button wire:click="create" icon="plus">New party list</x-ui.button>@endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($parties as $party)
            <x-ui.card wire:key="party-{{ $party->id }}">
                <div class="flex items-start gap-3">
                    @if ($party->logoUrl())
                        <img src="{{ $party->logoUrl() }}" alt="" class="size-12 rounded-lg object-contain ring-1 ring-slate-200">
                    @else
                        <span class="flex size-12 items-center justify-center rounded-lg text-sm font-bold text-white" style="background-color: {{ $party->color }}">{{ mb_substr($party->acronym ?: $party->name, 0, 3) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900">{{ $party->name }}</p>
                        <p class="text-xs text-slate-500">{{ $party->acronym }} · {{ $party->candidates->count() }} member(s)</p>
                    </div>
                    @can('manage-elections')
                        @if ($editable)
                            <button wire:click="edit({{ $party->id }})" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Edit {{ $party->name }}"><x-lucide-pencil class="size-4" /></button>
                            <button wire:click="delete({{ $party->id }})" wire:confirm="Delete {{ $party->name }}? Members become independent candidates." class="rounded p-1.5 text-rose-500 hover:bg-rose-50" aria-label="Delete {{ $party->name }}"><x-lucide-trash-2 class="size-4" /></button>
                        @endif
                    @endcan
                </div>
                @if ($party->description)<p class="mt-3 text-sm text-slate-600">{{ $party->description }}</p>@endif
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($party->candidates as $c)
                        <li class="flex justify-between gap-2"><span>{{ $c->display_name }}</span><span class="text-xs text-slate-500">{{ $c->position->name }}</span></li>
                    @empty
                        <li class="text-slate-400">No members yet. Assign candidates on the Candidates tab.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        @endforeach

        <x-ui.card>
            <div class="flex items-center gap-3">
                <span class="flex size-12 items-center justify-center rounded-lg bg-slate-500 text-sm font-bold text-white">IND</span>
                <div>
                    <p class="font-semibold text-slate-900">Independent candidates</p>
                    <p class="text-xs text-slate-500">{{ $independents->count() }} candidate(s)</p>
                </div>
            </div>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($independents as $c)
                    <li class="flex justify-between gap-2"><span>{{ $c->display_name }}</span><span class="text-xs text-slate-500">{{ $c->position->name }}</span></li>
                @empty
                    <li class="text-slate-400">None.</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit party list' : 'New party list'">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="name" label="Name" wire:model="name" class="sm:col-span-2" />
            <x-ui.input name="acronym" label="Acronym" wire:model="acronym" />
            <div>
                <label for="color" class="form-label">Colour</label>
                <input id="color" type="color" wire:model="color" class="h-11 w-full rounded-lg border border-slate-300">
                @error('color') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-ui.textarea name="description" label="Description" wire:model="description" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label for="logo" class="form-label">Logo (JPG/PNG/WebP, max 2 MB)</label>
                <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                @error('logo') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
