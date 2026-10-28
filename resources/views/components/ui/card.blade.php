@props(['title' => null, 'description' => null, 'padding' => true, 'actions' => null])
<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200']) }}>
    @if ($title)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
                @if ($description)<p class="text-sm text-slate-500">{{ $description }}</p>@endif
            </div>
            @if ($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif
        </div>
    @endif
    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
