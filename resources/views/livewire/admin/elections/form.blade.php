<div>
    <x-ui.page-header :title="$election ? 'Edit election' : 'New election'" description="All times are Philippine time (Asia/Manila)." />

    @if ($locked)
        <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            This election is {{ $election->status->label() }}. The schedule, school year and eligible grades are locked to protect ballots already cast.
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Details">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input name="name" label="Election name" wire:model="name" placeholder="SSLG Election 2026–2027" class="md:col-span-2" required />
                <x-ui.select name="type" label="Election type" wire:model="type" :disabled="$locked">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="school_year_id" label="School year" wire:model="school_year_id" :disabled="$locked">
                    <option value="">Select…</option>
                    @foreach ($schoolYears as $sy)
                        <option value="{{ $sy->id }}">{{ $sy->name }}{{ $sy->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.textarea name="description" label="Description (shown to students)" wire:model="description" class="md:col-span-2" />
            </div>
        </x-ui.card>

        <x-ui.card title="Schedule and eligibility">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input type="datetime-local" name="starts_at" label="Voting starts" wire:model="starts_at" :disabled="$locked" />
                <x-ui.input type="datetime-local" name="ends_at" label="Voting ends" wire:model="ends_at" :disabled="$locked" />
                <x-ui.grade-checkboxes label="Grade levels that vote in this election" model="eligible_grades" class="md:col-span-2" />
            </div>
        </x-ui.card>

        <x-ui.card title="Rules and branding">
            <div class="grid gap-4">
                <x-ui.textarea name="tie_resolution_rule" label="Tie-resolution rule (approved by the election committee)" wire:model="tie_resolution_rule" rows="3"
                               help="Printed on the canvassing report. Ties that decide a winner are never broken automatically." />
                <div>
                    <label for="banner" class="form-label">Election banner (optional, JPG/PNG/WebP, max 2 MB)</label>
                    <input id="banner" type="file" wire:model="banner" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium">
                    @error('banner') <p class="form-error">{{ $message }}</p> @enderror
                    @if ($banner && ! $errors->has('banner'))
                        <img src="{{ $banner->temporaryUrl() }}" alt="Banner preview" class="mt-2 h-24 rounded-lg object-cover">
                    @elseif ($election?->bannerUrl())
                        <img src="{{ $election->bannerUrl() }}" alt="Current banner" class="mt-2 h-24 rounded-lg object-cover">
                    @endif
                </div>
                @unless ($election)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="copy_positions" class="rounded border-slate-300 text-brand focus:ring-brand">
                        Add the default positions (President, Vice President, … Grade 12 Representative)
                    </label>
                @endunless
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="$election ? route('admin.elections.show', $election) : route('admin.elections.index')" wire:navigate>Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check" wire:loading.attr="disabled">Save election</x-ui.button>
        </div>
    </form>
</div>
