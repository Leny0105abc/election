<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (! School::query()->exists()) {
            School::create([
                'name' => 'Nancapian National High School',
                'primary_color' => '#1d4ed8',
                'secondary_color' => '#facc15',
            ]);
        }

        SchoolYear::ensureCurrent();
    }
}
