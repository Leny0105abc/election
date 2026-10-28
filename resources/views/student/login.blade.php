<x-layouts.guest title="Student Sign In">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900">Student voter sign in</h2>
        <p class="mt-1 text-sm text-slate-600">Use your Student ID or LRN and the PIN given by the election committee.</p>

        <div class="mt-5"><x-flash /></div>

        <form method="POST" action="{{ route('student.login.store') }}" class="space-y-4" x-data="{ busy: false, show: false }" @submit="busy = true">
            @csrf
            <x-ui.input name="identifier" label="Student ID or LRN" value="{{ old('identifier') }}"
                        autocomplete="username" autocapitalize="characters" required autofocus
                        class="[&_input]:min-h-12 [&_input]:text-base" />

            <div>
                <label for="pin" class="form-label">PIN</label>
                <div class="relative">
                    <input id="pin" name="pin" :type="show ? 'text' : 'password'" inputmode="numeric" autocomplete="current-password"
                           required class="form-input min-h-12 pr-12 text-base tracking-widest" @error('pin') aria-invalid="true" @enderror>
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500 hover:text-slate-800"
                            :aria-label="show ? 'Hide PIN' : 'Show PIN'">
                        <x-lucide-eye class="size-5" />
                    </button>
                </div>
                @error('pin') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <x-ui.button type="submit" size="lg" class="w-full" x-bind:disabled="busy">
                <span x-show="!busy">Sign in to vote</span>
                <span x-cloak x-show="busy">Signing in…</span>
            </x-ui.button>
        </form>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-xs text-slate-600">
            <p class="flex items-start gap-2"><x-lucide-shield-check class="size-4 shrink-0 text-emerald-600" />
                Never share your PIN. Election staff will never ask for it. Lost your PIN? Ask the election committee to reset it.</p>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-slate-500">
        Election staff? <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">Staff sign in</a>
    </p>
</x-layouts.guest>
