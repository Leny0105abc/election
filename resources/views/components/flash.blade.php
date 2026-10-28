@foreach (['success' => 'emerald', 'status' => 'sky', 'warning' => 'amber', 'error' => 'rose'] as $key => $color)
    @if (session($key))
        <div role="alert" @class([
            'mb-4 flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1',
            'bg-emerald-50 text-emerald-900 ring-emerald-200' => $color === 'emerald',
            'bg-sky-50 text-sky-900 ring-sky-200' => $color === 'sky',
            'bg-amber-50 text-amber-900 ring-amber-200' => $color === 'amber',
            'bg-rose-50 text-rose-900 ring-rose-200' => $color === 'rose',
        ])>
            @if ($color === 'rose' || $color === 'amber')
                <x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0" />
            @else
                <x-lucide-circle-check class="mt-0.5 size-4 shrink-0" />
            @endif
            <span>{{ session($key) }}</span>
        </div>
    @endif
@endforeach
