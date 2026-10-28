<?php

namespace App\Services;

use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Generates student PINs. Plain PINs exist only in memory for the duration
 * of the request that prints them; the database stores a bcrypt hash.
 */
class PinService
{
    /** Obvious PINs that are never issued. */
    private const WEAK = ['123456', '654321', '012345', '123123', '111222', '121212'];

    public function generatePin(): string
    {
        $length = max(4, (int) config('schoolvote.pin_length', 6));

        do {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= (string) random_int(0, 9);
            }
        } while ($this->isWeak($pin));

        return $pin;
    }

    private function isWeak(string $pin): bool
    {
        return count(array_unique(str_split($pin))) <= 2 || in_array($pin, self::WEAK, true);
    }

    /**
     * Assign new PINs. Returns the plain PINs so they can be printed once.
     *
     * @param  Collection<int, Student>  $students
     * @return list<array{student: Student, pin: string}>
     */
    public function assign(Collection $students): array
    {
        @set_time_limit(300);

        $rounds = (int) config('schoolvote.pin_hash_rounds', 10);
        $issued = [];
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($students, $rounds, &$issued, $now) {
            foreach ($students as $student) {
                $pin = $this->generatePin();

                Student::whereKey($student->id)->update([
                    'pin_hash' => Hash::make($pin, ['rounds' => $rounds]),
                    'pin_generated_at' => $now,
                ]);

                $issued[] = ['student' => $student, 'pin' => $pin];
            }
        });

        AuditLogger::log('students.pins_generated', null, ['count' => count($issued)], 'Generated '.count($issued).' student PIN(s)');

        return $issued;
    }
}
