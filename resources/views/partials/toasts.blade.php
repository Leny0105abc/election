{{-- Global toast area (Livewire: $this->dispatch('notify', message: '...', type: 'success|error|warning')) --}}
<div x-data="toasts" @notify.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end"
     aria-live="polite" role="status">
    <template x-for="item in items" :key="item.id">
        <div x-transition
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1"
             :class="{
                'bg-emerald-50 text-emerald-900 ring-emerald-200': item.type === 'success',
                'bg-rose-50 text-rose-900 ring-rose-200': item.type === 'error',
                'bg-amber-50 text-amber-900 ring-amber-200': item.type === 'warning',
             }">
            <span class="flex-1" x-text="item.message"></span>
            <button type="button" class="opacity-60 hover:opacity-100" @click="remove(item.id)" aria-label="Dismiss">&times;</button>
        </div>
    </template>
</div>
