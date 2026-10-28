@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'brand'])
@php
    $tones = [
        'brand' => 'bg-brand/10 text-brand',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200']) }}>
    <div class="flex items-center gap-3">
        @if ($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
                <x-dynamic-component :component="'lucide-'.$icon" class="size-5" />
            </span>
        @endif
        <div class="min-w-0">
            <p class="text-xs leading-tight font-medium tracking-wide text-slate-500 uppercase">{{ $label }}</p>
            <p class="text-2xl font-bold text-slate-900">{{ $value }}</p>
        </div>
    </div>
    @if ($hint)
        <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
