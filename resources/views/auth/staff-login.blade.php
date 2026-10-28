<x-layouts.guest title="Staff Sign In">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900">Election staff sign in</h2>
        <p class="mt-1 text-sm text-slate-600">For administrators and election committee members.</p>

        <div class="mt-5"><x-flash /></div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <x-ui.input name="email" type="email" label="Email address" value="{{ old('email') }}" autocomplete="username" required autofocus />
            <x-ui.input name="password" type="password" label="Password" autocomplete="current-password" required />

            <div class="flex items-center justify-between text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand focus:ring-brand"> Remember me
                </label>
                <a href="{{ route('password.request') }}" class="font-medium text-brand hover:underline">Forgot password?</a>
            </div>

            <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
        </form>
    </div>
    <p class="mt-6 text-center text-xs text-slate-500">
        Student? <a href="{{ route('student.login') }}" class="font-medium text-brand hover:underline">Go to student voting</a>
    </p>
</x-layouts.guest>
