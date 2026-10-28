<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', User::passwordRule(), 'confirmed'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $input['password']])->save();

        AuditLogger::log('staff.password_changed', $user);
    }
}
