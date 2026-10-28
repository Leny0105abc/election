@props(['title', 'description' => null, 'actions' => null])
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
    @if ($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</div>
