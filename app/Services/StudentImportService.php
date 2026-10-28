<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Imports\StudentRowsImport;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Two-step student import: preview() validates every row without saving,
 * commit() saves the valid rows in a single transaction.
 */
class StudentImportService
{
    public const HEADINGS = [
        'student_id', 'lrn', 'first_name', 'middle_name', 'last_name', 'suffix',
        'grade_level', 'section', 'enrollment_status', 'eligible',
    ];

    public const MAX_ROWS = 3000;

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array{total: int, new: int, update: int, error: int}}
     */
    public function preview(UploadedFile|string $file): array
    {
        $sheets = Excel::toArray(new StudentRowsImport, $file);
        $raw = $sheets[0] ?? [];

        if (count($raw) > self::MAX_ROWS) {
            throw new \InvalidArgumentException('The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.');
        }

        $seenIds = [];
        $seenLrns = [];
        $rows = [];

        $existingById = Student::pluck('id', 'student_number')->all();
        $existingByLrn = Student::whereNotNull('lrn')->pluck('id', 'lrn')->all();

        foreach ($raw as $index => $row) {
            $data = $this->normalize($row);

            if ($this->isBlank($data)) {
                continue;
            }

            $line = $index + 2; // heading row is line 1
            $errors = $this->validateRow($data);

            if ($data['student_id'] !== '' && isset($seenIds[$data['student_id']])) {
                $errors[] = "Duplicate Student ID in file (also on row {$seenIds[$data['student_id']]}).";
            }
            if ($data['lrn'] !== '' && isset($seenLrns[$data['lrn']])) {
                $errors[] = "Duplicate LRN in file (also on row {$seenLrns[$data['lrn']]}).";
            }

            $existingId = $existingById[$data['student_id']] ?? null;
            if ($data['lrn'] !== '' && isset($existingByLrn[$data['lrn']]) && $existingByLrn[$data['lrn']] !== $existingId) {
                $errors[] = 'LRN already belongs to a different student.';
            }

            $seenIds[$data['student_id']] = $line;
            if ($data['lrn'] !== '') {
                $seenLrns[$data['lrn']] = $line;
            }

            $rows[] = [
                'line' => $line,
                'data' => $data,
                'action' => $errors ? 'error' : ($existingId ? 'update' : 'new'),
                'errors' => $errors,
            ];
        }

        $summary = ['total' => count($rows), 'new' => 0, 'update' => 0, 'error' => 0];
        foreach ($rows as $r) {
            $summary[$r['action']]++;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * Save valid preview rows for the given school year.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function commit(array $rows, SchoolYear $schoolYear): array
    {
        $school = School::current();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $schoolYear, $school, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                if (($row['action'] ?? 'error') === 'error') {
                    $skipped++;

                    continue;
                }

                // Re-validate on the server; never trust preview data blindly.
                $data = $this->normalize($row['data']);
                if ($this->validateRow($data) !== []) {
                    $skipped++;

                    continue;
                }

                $student = Student::firstOrNew(['student_number' => $data['student_id']]);
                $isNew = ! $student->exists;
                $student->fill([
                    'school_id' => $school->id,
                    'lrn' => $data['lrn'] ?: null,
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?: null,
                    'last_name' => $data['last_name'],
                    'suffix' => $data['suffix'] ?: null,
                ]);
                $student->save();

                StudentEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'school_year_id' => $schoolYear->id],
                    [
                        'grade_level' => (int) $data['grade_level'],
                        'section' => $data['section'],
                        'enrollment_status' => $data['enrollment_status'],
                        'is_eligible' => $data['eligible'],
                    ],
                );

                $isNew ? $created++ : $updated++;
            }
        });

        AuditLogger::log('students.imported', null, compact('created', 'updated', 'skipped') + ['school_year' => $schoolYear->name]);

        return compact('created', 'updated', 'skipped');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $get = fn (string $key) => trim((string) ($row[$key] ?? ''));

        $status = strtolower($get('enrollment_status')) ?: EnrollmentStatus::Enrolled->value;
        $eligible = $row['eligible'] ?? '';
        $eligible = is_bool($eligible)
            ? $eligible
            : ! in_array(strtolower(trim((string) $eligible)), ['0', 'no', 'n', 'false', 'ineligible'], true);

        // Excel may turn a 12-digit LRN into a float like 1.2345E+11.
        $lrn = $get('lrn');
        if ($lrn !== '' && is_numeric($lrn)) {
            $lrn = number_format((float) $lrn, 0, '', '');
        }

        return [
            'student_id' => $get('student_id'),
            'lrn' => $lrn,
            'first_name' => $get('first_name'),
            'middle_name' => $get('middle_name'),
            'last_name' => $get('last_name'),
            'suffix' => $get('suffix'),
            'grade_level' => $get('grade_level'),
            'section' => $get('section'),
            'enrollment_status' => $status,
            'eligible' => $eligible,
        ];
    }

    /** @param array<string, mixed> $data */
    private function isBlank(array $data): bool
    {
        return $data['student_id'] === '' && $data['first_name'] === '' && $data['last_name'] === '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateRow(array $data): array
    {
        $validator = Validator::make($data, [
            'student_id' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'lrn' => ['nullable', 'digits:12'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'grade_level' => ['required', 'integer', 'in:'.implode(',', config('schoolvote.grade_levels'))],
            'section' => ['required', 'string', 'max:60'],
            'enrollment_status' => ['required', 'in:'.implode(',', array_column(EnrollmentStatus::cases(), 'value'))],
        ], [
            'student_id.regex' => 'Student ID may contain only letters, numbers and dashes.',
            'grade_level.in' => 'Grade level must be 7 to 12.',
        ]);

        return array_values($validator->errors()->all());
    }

    /**
     * Template rows (fictional examples).
     *
     * @return list<list<string>>
     */
    public static function templateRows(): array
    {
        return [
            ['2026-0001', '100000000001', 'Juan', 'Santos', 'Dela Cruz', '', '7', 'Sampaguita', 'enrolled', 'yes'],
            ['2026-0002', '', 'Maria', 'Reyes', 'Clara', '', '11', 'STEM-A', 'enrolled', 'yes'],
        ];
    }
}
