<div>
    <x-ui.page-header title="My account" :description="$user->name.' · '.$user->email.' · '.$user->role()?->label()" />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Two-factor authentication" description="Protects your account with a code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy).">
            @if ($enabled)
                <p class="mb-4 flex items-center gap-2 text-sm font-medium text-emerald-700"><x-lucide-shield-check class="size-5" /> Two-factor authentication is ON.</p>
                @if ($showingRecoveryCodes)
                    <p class="text-sm text-slate-600">Store these recovery codes somewhere safe. Each can be used once if you lose your phone.</p>
                    <div class="my-3 grid grid-cols-2 gap-1 rounded-xl bg-slate-900 p-4 font-mono text-sm text-white">
                        @foreach ($user->recoveryCodes() as $rc)<span>{{ $rc }}</span>@endforeach
                    </div>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" size="sm" wire:click="regenerateCodes" wire:confirm="Generate new recovery codes? Old codes stop working.">New recovery codes</x-ui.button>
                </div>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <x-ui.input type="password" name="confirmPassword" label="Current password (to turn off)" wire:model="confirmPassword" />
                    <x-ui.button variant="danger" size="sm" class="mt-2" wire:click="disableTwoFactor">Turn off two-factor</x-ui.button>
                </div>
            @elseif ($pending)
                <p class="text-sm text-slate-600">Scan this QR code with your authenticator app, then enter the 6-digit code.</p>
                <div class="my-4 inline-block rounded-xl bg-white p-3 ring-1 ring-slate-200">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <p class="text-xs text-slate-500">Setup key: <code class="select-all">{{ decrypt($user->two_factor_secret) }}</code></p>
                <div class="mt-4 flex items-end gap-2">
                    <x-ui.input name="code" label="6-digit code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" class="flex-1" />
                    <x-ui.button wire:click="confirmTwoFactor">Confirm</x-ui.button>
                </div>
            @else
                <p class="mb-3 text-sm text-amber-800">Two-factor authentication is OFF.</p>
                <x-ui.input type="password" name="confirmPassword" label="Current password" wire:model="confirmPassword" />
                <x-ui.button class="mt-3" icon="shield-check" wire:click="enableTwoFactor">Turn on two-factor</x-ui.button>
            @endif
        </x-ui.card>

        <x-ui.card title="Change password">
            <form wire:submit="updatePassword" class="space-y-4">
                <x-ui.input type="password" name="current_password" label="Current password" wire:model="current_password" autocomplete="current-password" />
                <x-ui.input type="password" name="password" label="New password" wire:model="password" autocomplete="new-password"
                            help="At least 12 characters with upper and lower case letters, a number and a symbol." />
                <x-ui.input type="password" name="password_confirmation" label="Confirm new password" wire:model="password_confirmation" autocomplete="new-password" />
                <x-ui.button type="submit" icon="lock">Update password</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
