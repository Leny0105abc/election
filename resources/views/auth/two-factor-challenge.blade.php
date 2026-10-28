<x-layouts.guest title="Two-Factor Authentication">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8" x-data="{ recovery: false }">
        <h2 class="text-xl font-bold text-slate-900">Two-factor authentication</h2>
        <p class="mt-1 text-sm text-slate-600" x-show="!recovery">Enter the 6-digit code from your authenticator app.</p>
        <p class="mt-1 text-sm text-slate-600" x-cloak x-show="recovery">Enter one of your emergency recovery codes.</p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-5 space-y-4">
            @csrf
            <div x-show="!recovery">
                <x-ui.input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>
            <div x-cloak x-show="recovery">
                <x-ui.input name="recovery_code" label="Recovery code" autocomplete="one-time-code" />
            </div>
            <x-ui.button type="submit" class="w-full">Verify</x-ui.button>
        </form>

        <button type="button" class="mt-4 text-sm font-medium text-brand hover:underline" @click="recovery = !recovery">
            <span x-show="!recovery">Use a recovery code instead</span>
            <span x-cloak x-show="recovery">Use an authentication code instead</span>
        </button>
    </div>
</x-layouts.guest>
