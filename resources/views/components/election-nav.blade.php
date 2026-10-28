@props(['election'])
@php
    $user = auth()->user();
    $tabs = [
        ['admin.elections.show', 'Overview', 'layout-dashboard', null],
        ['admin.elections.positions', 'Positions', 'list-ordered', null],
        ['admin.elections.party-lists', 'Party Lists', 'flag', null],
        ['admin.elections.candidates', 'Candidates', 'users-round', null],
        ['admin.elections.voters', 'Voter Roll', 'user-check', 'view-voter-reports'],
        ['admin.elections.results', 'Results', 'trophy', 'view-canvass'],
        ['admin.elections.reports', 'Reports', 'file-text', 'view-reports'],
    ];
@endphp
<div class="mb-6">
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.elections.index') }}" wire:navigate class="hover:text-slate-800">Elections</a>
        <x-lucide-chevron-right class="size-4" />
        <span class="font-medium text-slate-800">{{ $election->name }}</span>
        <x-ui.badge :color="$election->status->badgeClasses()">{{ $election->status->label() }}</x-ui.badge>
    </div>
    <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Election sections">
        <div class="flex min-w-max gap-1 border-b border-slate-200">
            @foreach ($tabs as [$route, $label, $icon, $permission])
                @if (! $permission || $user->can($permission))
                    @php($active = request()->routeIs($route))
                    <a href="{{ route($route, $election) }}" wire:navigate @class([
                        '-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap',
                        'border-brand text-brand' => $active,
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' => ! $active,
                    ]) @if($active) aria-current="page" @endif>
                        <x-dynamic-component :component="'lucide-'.$icon" class="size-4" /> {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
</div>
