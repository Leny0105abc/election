<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Staff self-service: change password and manage two-factor authentication.
 * Sensitive changes require the current password.
 */
#[Layout('components.layouts.admin')]
#[Title('My account')]
class Account extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $confirmPassword = '';

    public string $code = '';

    public bool $showingRecoveryCodes = false;

    private function verifyPassword(string $field, string $value): bool
    {
        if (! Hash::check($value, auth()->user()->password)) {
            $this->addError($field, 'The password is incorrect.');

            return false;
        }

        return true;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', User::passwordRule()],
        ]);

        if (! $this->verifyPassword('current_password', $this->current_password)) {
            return;
        }

        auth()->user()->forceFill(['password' => $this->password])->save();
        AuditLogger::log('staff.password_changed', auth()->user());
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('notify', message: 'Password updated.', type: 'success');
    }

    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $this->validate(['confirmPassword' => 'required|string']);
        if (! $this->verifyPassword('confirmPassword', $this->confirmPassword)) {
            return;
        }

        $enable(auth()->user());
        $this->confirmPassword = '';
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => 'required|string|size:6']);

        try {
            $confirm(auth()->user(), $this->code);
        } catch (ValidationException $e) {
            $this->addError('code', 'That code is not valid. Check your authenticator app and try again.');

            return;
        }

        AuditLogger::log('staff.two_factor_enabled', auth()->user());
        $this->code = '';
        $this->showingRecoveryCodes = true;
        $this->dispatch('notify', message: 'Two-factor authentication is on.', type: 'success');
    }

    public function regenerateCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(auth()->user());
        AuditLogger::log('staff.two_factor_codes_regenerated', auth()->user());
        $this->showingRecoveryCodes = true;
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $this->validate(['confirmPassword' => 'required|string']);
        if (! $this->verifyPassword('confirmPassword', $this->confirmPassword)) {
            return;
        }

        if (config('schoolvote.require_staff_two_factor')) {
            $this->addError('confirmPassword', 'Two-factor authentication is required for staff accounts on this system.');

            return;
        }

        $disable(auth()->user());
        AuditLogger::log('staff.two_factor_disabled', auth()->user());
        $this->confirmPassword = '';
        $this->showingRecoveryCodes = false;
        $this->dispatch('notify', message: 'Two-factor authentication turned off.', type: 'warning');
    }

    public function render()
    {
        $user = auth()->user()->fresh();

        return view('livewire.admin.account', [
            'user' => $user,
            'pending' => $user->two_factor_secret && ! $user->two_factor_confirmed_at,
            'enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }
}
