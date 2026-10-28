<x-layouts.guest title="Forgot Password">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900">Reset staff password</h2>
        <p class="mt-1 text-sm text-slate-600">Enter your staff email. If an account exists, we will send a reset link.</p>
        <div class="mt-5"><x-flash /></div>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-ui.input name="email" type="email" label="Email address" value="{{ old('email') }}" required autofocus />
            <x-ui.button type="submit" class="w-full">Email reset link</x-ui.button>
        </form>
        <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-brand hover:underline">Back to sign in</a></p>
        <p class="mt-4 text-xs text-slate-500">Students: PINs are reset by the election committee, not by email.</p>
    </div>
</x-layouts.guest>
