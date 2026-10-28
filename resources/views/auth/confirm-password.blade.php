<x-layouts.guest title="Confirm Password">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900">Confirm your password</h2>
        <p class="mt-1 text-sm text-slate-600">This is a secure area. Please confirm your password to continue.</p>
        <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-5 space-y-4">
            @csrf
            <x-ui.input name="password" type="password" label="Password" autocomplete="current-password" required autofocus />
            <x-ui.button type="submit" class="w-full">Confirm</x-ui.button>
        </form>
    </div>
</x-layouts.guest>
