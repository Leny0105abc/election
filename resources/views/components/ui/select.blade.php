@props(['label' => null, 'name', 'help' => null])
@php($id = $attributes->get('id', 'f-'.str_replace(['.', '[', ']'], '-', $name)))
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'form-input']) }}
            @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
        {{ $slot }}
    </select>
    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="form-error">{{ $message }}</p>
    @enderror
</div>
