<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seeding: roles, the default school / school year and the
 * default position catalogue. Idempotent — safe to run more than once.
 *
 * It creates NO accounts and NO students. Create the first Super
 * Administrator with:  php artisan schoolvote:create-admin
 *
 * Fictional demo data (local only):  php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SchoolSeeder::class,
            PositionSeeder::class,
        ]);
    }
}
