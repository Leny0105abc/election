@props(['label', 'model', 'help' => null])
<fieldset {{ $attributes->only('class') }}>
    <legend class="form-label">{{ $label }}</legend>
    <div class="flex flex-wrap gap-2">
        @foreach (config('schoolvote.grade_levels') as $grade)
            <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/10">
                <input type="checkbox" value="{{ $grade }}" wire:model="{{ $model }}" class="rounded border-slate-300 text-brand focus:ring-brand">
                Grade {{ $grade }}
            </label>
        @endforeach
    </div>
    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($model)
        <p class="form-error">{{ $message }}</p>
    @enderror
</fieldset>
