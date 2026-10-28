{{-- Usage: <x-ui.modal wire:model="showForm" title="..."> ... <x-slot:footer>...</x-slot:footer></x-ui.modal> --}}
@props(['title' => null, 'maxWidth' => 'max-w-lg', 'footer' => null])
@php($model = $attributes->wire('model')->value())
<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4"
         @keydown.escape.window="open = false" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
        <div x-show="open" x-transition @click.outside="open = false"
             class="flex max-h-[92vh] w-full {{ $maxWidth }} flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
            @if ($title)
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    <button type="button" class="rounded p-1 text-slate-400 hover:text-slate-700" @click="open = false" aria-label="Close">
                        <x-lucide-x class="size-5" />
                    </button>
                </div>
            @endif
            <div class="overflow-y-auto px-5 py-4">
                {{ $slot }}
            </div>
            @if ($footer)
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
