@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
@php
    $school = school();
    $user = auth()->user();
    $nav = [
        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'can' => null],
        ['route' => 'admin.elections.index', 'match' => 'admin.elections.*', 'label' => 'Elections', 'icon' => 'vote', 'can' => null],
        ['route' => 'admin.positions', 'match' => 'admin.positions', 'label' => 'Positions', 'icon' => 'list-ordered', 'can' => 'manage-elections'],
        ['route' => 'admin.students.index', 'match' => 'admin.students.*', 'label' => 'Students', 'icon' => 'users', 'can' => 'manage-students'],
        ['route' => 'admin.audit-logs', 'match' => 'admin.audit-logs', 'label' => 'Audit Log', 'icon' => 'history', 'can' => 'view-audit'],
        ['route' => 'admin.users', 'match' => 'admin.users', 'label' => 'Staff Accounts', 'icon' => 'user-cog', 'can' => 'manage-users'],
        ['route' => 'admin.settings', 'match' => 'admin.settings', 'label' => 'School Settings', 'icon' => 'school', 'can' => 'manage-settings'],
    ];
@endphp
<div x-data="{ sidebar: false }" class="min-h-full">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-white focus:p-2">Skip to content</a>

    {{-- Mobile backdrop --}}
    <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="sidebar = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-200 transition-transform lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : ''" aria-label="Main navigation">
        <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="" class="size-10 rounded-full bg-white object-contain p-0.5">
            @else
                <span class="flex size-10 items-center justify-center rounded-full bg-brand text-white"><x-lucide-vote class="size-5" /></span>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-bold text-white">SchoolVote</p>
                <p class="truncate text-xs text-slate-400">{{ $school->name }}</p>
            </div>
            <button type="button" class="ml-auto rounded p-1 text-slate-400 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Close menu">
                <x-lucide-x class="size-5" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as $item)
                @if (! $item['can'] || $user->can($item['can']))
                    @php($active = request()->routeIs($item['match']))
                    <a href="{{ route($item['route']) }}" wire:navigate @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-white/10 text-white' => $active,
                        'text-slate-300 hover:bg-white/5 hover:text-white' => ! $active,
                    ]) @if($active) aria-current="page" @endif>
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <a href="{{ route('admin.account') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">
                <span class="flex size-8 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-slate-400">{{ $user->role()?->label() }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">
                    <x-lucide-log-out class="size-5" /> Sign out
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Top bar (mobile) --}}
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100" @click="sidebar = true" aria-label="Open menu">
                <x-lucide-menu class="size-6" />
            </button>
            <span class="truncate font-semibold">SchoolVote</span>
        </header>

        <main id="main" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
@include('partials.toasts')
</body>
</html>
