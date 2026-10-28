<div>
    <x-ui.page-header title="Staff accounts" description="Super Administrators, Election Administrators and Election Committee members. There is no public sign-up.">
        <x-slot:actions><x-ui.button wire:click="create" icon="plus">Add staff</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="table-base">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2FA</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td class="font-medium text-slate-900">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->role()?->label() ?? '—' }}</td>
                        <td>{!! $u->hasTwoFactorEnabled() ? '<span class="text-emerald-700">On</span>' : '<span class="text-amber-700">Off</span>' !!}</td>
                        <td class="text-xs">{{ local_dt($u->last_login_at) ?: 'Never' }}</td>
                        <td>{!! $u->is_active ? '<span class="text-emerald-700">Active</span>' : '<span class="text-slate-400">Inactive</span>' !!}</td>
                        <td class="text-right"><button wire:click="edit({{ $u->id }})" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Edit {{ $u->name }}"><x-lucide-pencil class="size-4" /></button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6 grid gap-3 md:grid-cols-3">
        @foreach ($roles as $r)
            <div class="rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
                <p class="font-semibold text-slate-900">{{ $r->label() }}</p>
                <p class="text-slate-600">{{ $r->description() }}</p>
            </div>
        @endforeach
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit staff account' : 'Add staff account'">
        <div class="grid gap-4">
            <x-ui.input name="name" label="Full name" wire:model="name" />
            <x-ui.input name="email" type="email" label="Email" wire:model="email" />
            <x-ui.select name="role" label="Role" wire:model="role">
                @foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
            </x-ui.select>
            <x-ui.input name="password" type="password" :label="$editingId ? 'New password (leave blank to keep)' : 'Initial password'" wire:model="password" autocomplete="new-password"
                        help="At least 12 characters with upper and lower case letters, a number and a symbol. Ask the user to change it after first sign-in." />
            <x-ui.input name="password_confirmation" type="password" label="Confirm password" wire:model="password_confirmation" autocomplete="new-password" />
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand focus:ring-brand"> Account active</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
