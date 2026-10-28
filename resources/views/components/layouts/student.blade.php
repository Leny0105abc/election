@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-800 antialiased">
    @php($school = school())
    @php($student = auth('student')->user())
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-white focus:p-2">Skip to content</a>

    <header class="bg-brand text-white shadow">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
            <a href="{{ route('student.portal') }}" class="flex min-w-0 flex-1 items-center gap-3">
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="" class="size-10 shrink-0 rounded-full bg-white object-contain p-0.5">
                @else
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/15"><x-lucide-vote class="size-5" /></span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold leading-tight">{{ $school->name }}</span>
                    <span class="block text-xs text-white/80">SchoolVote</span>
                </span>
            </a>
            @if ($student)
                <form method="POST" action="{{ route('student.logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-white/15 px-3 text-sm font-medium hover:bg-white/25 focus:ring-2 focus:ring-white focus:outline-none">
                        <x-lucide-log-out class="size-4" /> <span>Sign out</span>
                    </button>
                </form>
            @endif
        </div>
    </header>

    @if ($student)
        <div class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm">
                <span class="font-semibold text-slate-900">{{ $student->full_name }}</span>
                @if ($student->currentEnrollment)
                    <span class="text-slate-500">Grade {{ $student->currentEnrollment->grade_level }} · {{ $student->currentEnrollment->section }}</span>
                @endif
            </div>
        </div>
    @endif

    <main id="main" class="mx-auto max-w-3xl px-4 py-6">
        <x-flash />
        {{ $slot }}
    </main>

    @include('partials.toasts')
</body>
</html>
