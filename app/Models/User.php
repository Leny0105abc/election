<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Staff account (Super Administrator, Election Administrator, Election Committee).
 *
 * @property int $id
 * @property int|null $role_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property-read UserRole|null $userRole
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'password', 'role_id', 'is_active'];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<UserRole, $this> */
    public function userRole(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role_id');
    }

    public function role(): ?Role
    {
        return Role::tryFrom((string) $this->userRole?->slug);
    }

    public function hasRole(Role $role): bool
    {
        return $this->role() === $role;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && (bool) $this->role()?->can($permission);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /** Strong password policy for staff accounts. */
    public static function passwordRule(): Password
    {
        $rule = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }
}
