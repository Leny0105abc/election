@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-800 antialiased">
    @php($school = school())
    <div class="flex min-h-screen flex-col">
        <div class="h-1.5 bg-brand"></div>
        <main class="flex flex-1 flex-col items-center justify-center px-4 py-10">
            <div class="mb-6 flex flex-col items-center text-center">
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }} logo" class="mb-3 size-20 rounded-full bg-white object-contain p-1 shadow">
                @else
                    <div class="mb-3 flex size-16 items-center justify-center rounded-full bg-brand text-white shadow">
                        <x-lucide-vote class="size-8" />
                    </div>
                @endif
                <p class="text-xs font-semibold tracking-widest text-brand uppercase">SchoolVote</p>
                <h1 class="text-lg font-bold text-slate-900 sm:text-xl">{{ $school->name }}</h1>
            </div>

            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </main>
        <footer class="px-4 pb-6 text-center text-xs text-slate-500">
            Your vote is secret. SchoolVote never links your choices to your name.
        </footer>
    </div>
</body>
</html>
