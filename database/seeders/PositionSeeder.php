<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Default SSLG positions. Grade representatives are voted on (and run) only
 * by students of that grade; administrators may change this before voting.
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['President', 'Chief executive of the Supreme Secondary Learner Government.'],
            ['Vice President', 'Assists the President and acts in their absence.'],
            ['Secretary', 'Keeps minutes and official records.'],
            ['Treasurer', 'Manages SSLG funds and financial records.'],
            ['Auditor', 'Reviews and verifies SSLG finances.'],
            ['Public Information Officer (PIO)', 'Handles announcements and communication.'],
            ['Protocol Officer', 'Maintains order and decorum during activities.'],
        ];

        $order = 1;
        foreach ($positions as [$name, $description]) {
            Position::firstOrCreate(['name' => $name], [
                'description' => $description,
                'display_order' => $order++,
                'max_winners' => 1,
                'allow_abstain' => true,
                'is_active' => true,
            ]);
        }

        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            Position::firstOrCreate(['name' => "Grade {$grade} Representative"], [
                'description' => "Represents Grade {$grade} learners.",
                'display_order' => $order++,
                'max_winners' => 1,
                'eligible_voter_grades' => [$grade],
                'eligible_candidate_grades' => [$grade],
                'allow_abstain' => true,
                'is_active' => true,
            ]);
        }

        // Optional extras: available in the catalogue but not added to new elections by default.
        foreach (['Peace Officer', 'Business Manager', 'Sergeant-at-Arms'] as $extra) {
            Position::firstOrCreate(['name' => $extra], [
                'display_order' => $order++,
                'max_winners' => 1,
                'allow_abstain' => true,
                'is_active' => false,
            ]);
        }
    }
}
