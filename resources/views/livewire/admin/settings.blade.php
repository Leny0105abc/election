<div>
    <x-ui.page-header title="School settings" description="Branding and details used on every page and report." />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="School information" class="lg:col-span-2">
            <form wire:submit="saveSchool" class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="name" label="School name" wire:model="name" class="sm:col-span-2" />
                <x-ui.input name="school_code" label="DepEd School ID" wire:model="school_code" />
                <x-ui.input name="address" label="Address" wire:model="address" />
                <x-ui.input name="division" label="Schools Division" wire:model="division" />
                <x-ui.input name="region" label="Region" wire:model="region" />
                <div>
                    <label for="pc" class="form-label">Primary colour</label>
                    <input id="pc" type="color" wire:model="primary_color" class="h-11 w-full rounded-lg border border-slate-300">
                    @error('primary_color') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sc" class="form-label">Accent colour</label>
                    <input id="sc" type="color" wire:model="secondary_color" class="h-11 w-full rounded-lg border border-slate-300">
                </div>
                <div class="sm:col-span-2">
                    <label for="logo" class="form-label">School logo (PNG/JPG/WebP, max 2 MB)</label>
                    <div class="flex items-center gap-4">
                        @if ($logo && ! $errors->has('logo'))
                            <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="size-16 rounded-full bg-white object-contain ring-1 ring-slate-200">
                        @elseif ($school->logoUrl())
                            <img src="{{ $school->logoUrl() }}" alt="Current logo" class="size-16 rounded-full bg-white object-contain ring-1 ring-slate-200">
                        @endif
                        <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                    </div>
                    @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end"><x-ui.button type="submit" icon="check">Save school information</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card title="School years">
            <ul class="mb-4 divide-y divide-slate-100 text-sm">
                @foreach ($schoolYears as $sy)
                    <li class="flex items-center justify-between py-2" wire:key="sy-{{ $sy->id }}">
                        <span><strong>{{ $sy->name }}</strong> <span class="text-xs text-slate-500">· {{ $sy->enrollments_count }} enrolled</span></span>
                        @if ($sy->is_current)
                            <x-ui.badge color="bg-emerald-50 text-emerald-700 ring-emerald-300">Current</x-ui.badge>
                        @else
                            <button wire:click="makeCurrent({{ $sy->id }})" class="text-xs font-medium text-brand hover:underline">Make current</button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <form wire:submit="addSchoolYear" class="flex gap-2">
                <x-ui.input name="newSchoolYear" wire:model="newSchoolYear" placeholder="2027-2028" class="flex-1" aria-label="New school year" />
                <x-ui.button type="submit" variant="secondary" icon="plus">Add</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Report signatories" description="Printed at the bottom of every PDF and printable report." class="lg:col-span-3">
            <form wire:submit="saveSignatories">
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($signatories as $i => $sig)
                        <div class="space-y-2 rounded-xl bg-slate-50 p-3" wire:key="sig-{{ $i }}">
                            <x-ui.input name="signatories.{{ $i }}.label" label="Label" wire:model="signatories.{{ $i }}.label" />
                            <x-ui.input name="signatories.{{ $i }}.name" label="Name" wire:model="signatories.{{ $i }}.name" />
                            <x-ui.input name="signatories.{{ $i }}.title" label="Title / designation" wire:model="signatories.{{ $i }}.title" />
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-end"><x-ui.button type="submit" icon="check">Save signatories</x-ui.button></div>
            </form>
        </x-ui.card>
    </div>
</div>
