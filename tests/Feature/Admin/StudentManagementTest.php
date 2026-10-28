<?php

use App\Enums\Role;
use App\Livewire\Admin\Students\Import;
use App\Livewire\Admin\Students\Index;
use App\Livewire\Admin\Students\Pins;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\PinService;
use App\Services\StudentImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function csvUpload(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'sv').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'students.csv', 'text/csv', null, true);
}

$header = "student_id,lrn,first_name,middle_name,last_name,suffix,grade_level,section,enrollment_status,eligible\n";

it('previews an import, flags errors and duplicates, then commits valid rows', function () use ($header) {
    makeStudent(8, '111111', ['student_number' => 'EXIST-001', 'lrn' => '111111111111']);

    $csv = $header
        ."NEW-001,222222222222,Ana,Reyes,Santos,,7,Sampaguita,enrolled,yes\n"
        ."NEW-002,,Ben,,Cruz,Jr.,13,Rosal,enrolled,yes\n"              // bad grade
        ."NEW-001,,Dup,,Row,,7,Rosal,enrolled,yes\n"                    // duplicate ID in file
        ."EXIST-001,111111111111,Updated,,Name,,9,Rizal,enrolled,no\n"  // update existing
        ."NEW-003,111111111111,Lrn,,Clash,,9,Rizal,enrolled,yes\n";     // LRN belongs to another student

    $service = app(StudentImportService::class);
    $preview = $service->preview(csvUpload($csv));

    expect($preview['summary'])->toBe(['total' => 5, 'new' => 1, 'update' => 1, 'error' => 3]);

    $result = $service->commit($preview['rows'], SchoolYear::current());
    expect($result)->toBe(['created' => 1, 'updated' => 1, 'skipped' => 3]);

    $updated = Student::firstWhere('student_number', 'EXIST-001');
    expect($updated->first_name)->toBe('Updated')
        ->and($updated->enrollments()->first()->grade_level)->toBe(9)
        ->and($updated->enrollments()->first()->is_eligible)->toBeFalse()
        ->and(Student::where('student_number', 'NEW-001')->exists())->toBeTrue();
});

it('runs the import through the Livewire page', function () use ($header) {
    $file = UploadedFile::fake()->createWithContent('students.csv', $header."LW-001,,Carla,,Lopez,,10,Diamond,enrolled,yes\n");

    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Import::class)
        ->set('file', $file)
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSet('summary.new', 1)
        ->call('commit')
        ->assertSet('result.created', 1);

    expect(Student::where('student_number', 'LW-001')->exists())->toBeTrue();
});

it('downloads the import template', function () {
    $this->actingAs(staff(Role::ElectionAdmin))->get(route('admin.students.template'))
        ->assertOk()
        ->assertDownload('schoolvote-student-import-template.xlsx');
});

it('generates unique-looking numeric PINs and stores only hashes', function () {
    $students = collect([makeStudent(), makeStudent(), makeStudent()]);
    $issued = app(PinService::class)->assign($students);

    foreach ($issued as $item) {
        $hash = $item['student']->fresh()->pin_hash;
        expect($item['pin'])->toMatch('/^\d{6}$/')
            ->and($hash)->not->toBe($item['pin'])
            ->and(Hash::check($item['pin'], $hash))->toBeTrue();
    }
});

it('never issues obviously weak PINs', function () {
    $service = app(PinService::class);
    foreach (range(1, 300) as $i) {
        $pin = $service->generatePin();
        expect(count(array_unique(str_split($pin))))->toBeGreaterThan(2)
            ->and($pin)->not->toBe('123456');
    }
});

it('downloads printable PIN slips from the PINs page', function () {
    makeStudent(7);
    Student::query()->update(['pin_hash' => null]);

    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Pins::class)
        ->set('grade', '7')
        ->call('generate')
        ->assertFileDownloaded();

    expect(Student::whereNull('pin_hash')->count())->toBe(0);
});

it('resets one student PIN and shows it once', function () {
    $student = makeStudent(10, '135790');

    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Index::class)
        ->call('resetPin', $student->id)
        ->assertSet('showPin', true)
        ->assertSee($student->full_name)
        ->call('closePin')
        ->assertSet('issuedPin', '');

    expect(Hash::check('135790', $student->fresh()->pin_hash))->toBeFalse();
});

it('adds and edits students with validation', function () {
    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Index::class)
        ->call('create')
        ->set('student_number', 'ADD-001')
        ->set('lrn', '12345')               // invalid LRN
        ->set('first_name', 'Dana')
        ->set('last_name', 'Villanueva')
        ->set('grade_level', 11)
        ->set('student_section', 'STEM-A')
        ->call('save')
        ->assertHasErrors(['lrn'])
        ->set('lrn', '')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::firstWhere('student_number', 'ADD-001');
    expect($student->enrollments()->first()->section)->toBe('STEM-A');
});

it('blocks committee members from student management actions', function () {
    $student = makeStudent();
    Livewire::actingAs(staff(Role::Committee))->test(Index::class)->assertForbidden();
});
