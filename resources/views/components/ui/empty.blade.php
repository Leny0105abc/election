@props(['icon' => 'info', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white px-6 py-12 text-center']) }}>
    <x-dynamic-component :component="'lucide-'.$icon" class="mb-3 size-10 text-slate-300" />
    <p class="font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
