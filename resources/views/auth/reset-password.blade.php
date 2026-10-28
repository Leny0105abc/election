<x-layouts.guest title="Reset Password">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900">Choose a new password</h2>
        <p class="mt-1 text-sm text-slate-600">At least 12 characters with upper and lower case letters, a number and a symbol.</p>
        <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-ui.input name="email" type="email" label="Email address" value="{{ old('email', $request->email) }}" required />
            <x-ui.input name="password" type="password" label="New password" autocomplete="new-password" required />
            <x-ui.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
            <x-ui.button type="submit" class="w-full">Reset password</x-ui.button>
        </form>
    </div>
</x-layouts.guest>
