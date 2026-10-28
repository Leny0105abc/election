<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            UserRole::updateOrCreate(
                ['slug' => $role->value],
                ['name' => $role->label(), 'description' => $role->description()],
            );
        }
    }
}
