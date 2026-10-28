<div>
    <x-election-nav :election="$election" />

    <x-ui.page-header title="Reports" description="PDF, Excel and printable reports with school branding and signature blocks. Reports never include individual ballot choices.">
        @can('manage-settings')
            <p class="mt-1 text-xs text-slate-500">Edit signatories in <a href="{{ route('admin.settings') }}" class="text-brand hover:underline" wire:navigate>School Settings</a>.</p>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($items as $item)
            <div wire:key="r-{{ $item['key'] }}" class="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 {{ $item['reason'] ? 'opacity-70' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand"><x-lucide-file-text class="size-5" /></span>
                    <div>
                        <p class="font-semibold text-slate-900">{{ $item['title'] }}</p>
                        <p class="text-sm text-slate-600">{{ $item['description'] }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($item['reason'])
                        <p class="flex items-center gap-1.5 text-xs text-slate-500"><x-lucide-lock class="size-3.5" /> {{ $item['reason'] }}</p>
                    @else
                        <x-ui.button size="sm" variant="secondary" icon="file-down" :href="route('admin.reports.show', [$election, $item['key'], 'pdf'])">PDF</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="file-spreadsheet" :href="route('admin.reports.show', [$election, $item['key'], 'xlsx'])">Excel</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="printer" :href="route('admin.reports.show', [$election, $item['key'], 'print'])" target="_blank">Print</x-ui.button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
