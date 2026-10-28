@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'icon' => null])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
    $sizes = [
        'sm' => 'min-h-9 px-3 text-sm',
        'md' => 'min-h-11 px-4 text-sm',
        'lg' => 'min-h-14 px-6 text-base',
    ];
    $variants = [
        'primary' => 'bg-brand text-white shadow-sm hover:bg-brand-dark focus-visible:ring-brand',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-slate-300 shadow-sm hover:bg-slate-50 focus-visible:ring-slate-400',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:ring-rose-500',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:ring-emerald-500',
        'ghost' => 'text-slate-600 hover:bg-slate-100 focus-visible:ring-slate-400',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
