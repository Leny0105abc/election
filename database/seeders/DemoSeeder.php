<?php

namespace Database\Seeders;

use App\Enums\CandidateStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\PartyList;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ElectionService;
use Database\Factories\FilipinoNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FICTIONAL demo data for local development and acceptance testing:
 * ~500 students in Grades 7-12, three staff accounts, two party lists and
 * a draft SSLG election with approved candidates.
 *
 * Credentials are random and written to storage/app/private/demo-credentials.csv
 * (git-ignored). Never run against production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $schoolYear = SchoolYear::ensureCurrent();
        $credentials = [['type', 'login', 'secret', 'name']];

        // Staff accounts with random passwords.
        foreach ([
            [Role::SuperAdmin, 'superadmin@schoolvote.test', 'Super Admin'],
            [Role::ElectionAdmin, 'admin@schoolvote.test', 'Election Admin'],
            [Role::Committee, 'committee@schoolvote.test', 'Committee Member'],
        ] as [$role, $email, $name]) {
            $password = Str::password(16);
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'role_id' => UserRole::idFor($role), 'is_active' => true,
            ]);
            $credentials[] = ['staff', $email, $password, $name];
        }

        // ~504 fictional students: 6 grades x 3 sections x 28 students.
        $rounds = (int) config('schoolvote.pin_hash_rounds');
        $demoPin = '482915'; // shared demo PIN, local only (fast seeding)
        $pinHash = Hash::make($demoPin, ['rounds' => $rounds]);
        $counter = 1;
        $now = now();

        DB::transaction(function () use ($schoolYear, $pinHash, &$counter, $now) {
            foreach (FilipinoNames::SECTIONS as $grade => $sections) {
                foreach ($sections as $section) {
                    for ($i = 0; $i < 28; $i++) {
                        $number = sprintf('DEMO-%04d', $counter);
                        $student = Student::firstOrCreate(['student_number' => $number], [
                            'school_id' => $schoolYear->school_id,
                            'lrn' => sprintf('9%011d', $counter),
                            'first_name' => FilipinoNames::first(),
                            'middle_name' => FilipinoNames::last(),
                            'last_name' => FilipinoNames::last(),
                            'is_active' => true,
                        ]);
                        $student->forceFill(['pin_hash' => $pinHash, 'pin_generated_at' => $now])->save();

                        StudentEnrollment::updateOrCreate(
                            ['student_id' => $student->id, 'school_year_id' => $schoolYear->id],
                            ['grade_level' => $grade, 'section' => $section, 'enrollment_status' => EnrollmentStatus::Enrolled, 'is_eligible' => true],
                        );
                        $counter++;
                    }
                }
            }
        });
        $credentials[] = ['students', 'DEMO-0001 … DEMO-'.sprintf('%04d', $counter - 1), $demoPin, 'All demo students share this PIN'];

        // Demo election (draft) with party lists and candidates.
        $admin = User::where('email', 'superadmin@schoolvote.test')->first();
        $election = Election::where('name', 'SSLG Election')->first()
            ?? app(ElectionService::class)->create([
                'school_year_id' => $schoolYear->id,
                'name' => 'SSLG Election',
                'type' => 'sslg',
                'description' => 'Election of the Supreme Secondary Learner Government (SSLG) officers.',
                'starts_at' => now()->addDay()->setTime(0, 0),
                'ends_at' => now()->addDay()->setTime(9, 0),
                'eligible_grades' => [7, 8, 9, 10, 11, 12],
                'tie_resolution_rule' => 'Ties affecting the winners are resolved by toss coin conducted by the Election Committee.',
            ], $admin);

        if ($election->candidates()->doesntExist()) {
            $parties = [
                PartyList::create(['election_id' => $election->id, 'name' => 'Bagong Pag-asa', 'acronym' => 'BP', 'color' => '#2563eb']),
                PartyList::create(['election_id' => $election->id, 'name' => 'Lingkod Mag-aaral', 'acronym' => 'LM', 'color' => '#dc2626']),
            ];

            $used = [];
            foreach ($election->positions as $position) {
                /** @var ElectionPosition $position */
                $grades = $position->eligible_candidate_grades ?: [10, 11];
                foreach ($parties as $party) {
                    $enrollment = StudentEnrollment::where('school_year_id', $schoolYear->id)
                        ->whereIn('grade_level', $grades)
                        ->whereNotIn('student_id', $used)
                        ->inRandomOrder()->first();
                    $used[] = $enrollment->student_id;

                    $election->candidates()->create([
                        'election_position_id' => $position->id,
                        'student_id' => $enrollment->student_id,
                        'party_list_id' => $party->id,
                        'grade_level' => $enrollment->grade_level,
                        'platform' => 'Fictional platform: more school clubs, cleaner classrooms and a student feedback box.',
                        'status' => CandidateStatus::Approved,
                    ]);
                }
            }
        }

        $path = 'demo-credentials.csv';
        $stream = fopen('php://temp', 'r+');
        foreach ($credentials as $row) {
            fputcsv($stream, $row, escape: '\\');
        }
        rewind($stream);
        Storage::disk('local')->put($path, stream_get_contents($stream));
        fclose($stream);

        $this->command?->info('Demo data ready. Credentials saved to '.Storage::disk('local')->path($path));
    }
}
