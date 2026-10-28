<?php

use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

it('shows the student login page', function () {
    $this->get('/')->assertOk()->assertSee('Student voter sign in')->assertSee('name="viewport"', false);
});

it('signs a student in with Student ID and PIN', function () {
    $student = makeStudent(10, '246810');

    $this->post('/login', ['identifier' => $student->student_number, 'pin' => '246810'])
        ->assertRedirect(route('student.portal'));

    expect(Auth::guard('student')->id())->toBe($student->id)
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('signs a student in with LRN and PIN', function () {
    $student = makeStudent(10, '246810', ['lrn' => '123456789012']);

    $this->post('/login', ['identifier' => '123456789012', 'pin' => '246810'])
        ->assertRedirect(route('student.portal'));

    expect(Auth::guard('student')->id())->toBe($student->id);
});

it('rejects an invalid PIN with a generic message', function () {
    $student = makeStudent(10, '246810');

    $this->from('/')->post('/login', ['identifier' => $student->student_number, 'pin' => '999999'])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['identifier' => 'The Student ID/LRN or PIN is incorrect, or the account is not active.']);

    expect(Auth::guard('student')->check())->toBeFalse();
});

it('gives the same message for unknown IDs (no account enumeration)', function () {
    $this->from('/')->post('/login', ['identifier' => 'NOPE-0000', 'pin' => '123456'])
        ->assertSessionHasErrors(['identifier' => 'The Student ID/LRN or PIN is incorrect, or the account is not active.']);
});

it('refuses inactive student accounts', function () {
    $student = makeStudent(10, '246810');
    $student->update(['is_active' => false]);

    $this->post('/login', ['identifier' => $student->student_number, 'pin' => '246810'])
        ->assertSessionHasErrors('identifier');

    expect(Auth::guard('student')->check())->toBeFalse();
});

it('refuses students who have no PIN yet', function () {
    $student = Student::factory()->create();

    $this->post('/login', ['identifier' => $student->student_number, 'pin' => '000000'])
        ->assertSessionHasErrors('identifier');
});

it('rate limits repeated login attempts for the same ID', function () {
    $student = makeStudent(10, '246810');

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['identifier' => $student->student_number, 'pin' => '000001']);
    }

    $this->post('/login', ['identifier' => $student->student_number, 'pin' => '246810'])->assertStatus(429);
});

it('never stores or logs the PIN in plain text', function () {
    $student = makeStudent(10, '246810');
    $this->post('/login', ['identifier' => $student->student_number, 'pin' => '246810']);

    expect($student->fresh()->pin_hash)->not->toContain('246810')
        ->and(password_get_info($student->fresh()->pin_hash)['algoName'])->toBe('bcrypt')
        ->and(AuditLog::all()->toJson())->not->toContain('246810');
});

it('signs students out after inactivity', function () {
    $student = makeStudent();
    $this->actingAs($student, 'student')
        ->withSession(['student_last_activity' => time() - 3600])
        ->get(route('student.portal'))
        ->assertRedirect(route('student.login'));

    expect(Auth::guard('student')->check())->toBeFalse();
});

it('logs a student out', function () {
    $student = makeStudent();
    $this->actingAs($student, 'student')->post('/logout')->assertRedirect(route('student.login'));
    expect(Auth::guard('student')->check())->toBeFalse();
});

it('sends guests to the student login', function () {
    $this->get(route('student.portal'))->assertRedirect(route('student.login'));
});
