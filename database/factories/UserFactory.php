<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Password used by factory users in tests only. */
    public const TEST_PASSWORD = 'Test-Password-123!';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::TEST_PASSWORD),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'role_id' => fn () => UserRole::idFor(Role::ElectionAdmin),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role_id' => UserRole::idFor($role)]);
    }

    public function superAdmin(): static
    {
        return $this->role(Role::SuperAdmin);
    }

    public function committee(): static
    {
        return $this->role(Role::Committee);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
