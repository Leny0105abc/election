<?php

use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Users;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PositionSeeder;
use Livewire\Livewire;

/** Smoke test: every admin page renders for a Super Administrator. */
it('renders every admin page', function () {
    $this->seed(PositionSeeder::class);
    $s = votingElection(ElectionStatus::Closed);
    $admin = staff(Role::SuperAdmin);
    $e = $s['election'];

    $pages = [
        '/admin', '/admin/account', '/admin/elections', '/admin/elections/create',
        "/admin/elections/{$e->id}", "/admin/elections/{$e->id}/edit", "/admin/elections/{$e->id}/positions",
        "/admin/elections/{$e->id}/party-lists", "/admin/elections/{$e->id}/candidates", "/admin/elections/{$e->id}/voters",
        "/admin/elections/{$e->id}/results", "/admin/elections/{$e->id}/reports", '/admin/positions',
        '/admin/students', '/admin/students/import', '/admin/students/pins', '/admin/audit-logs', '/admin/users', '/admin/settings',
    ];

    foreach ($pages as $page) {
        $this->actingAs($admin)->get($page)->assertOk();
    }
});

it('renders student pages', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->actingAs($student, 'student')->withSession(['student_last_activity' => time()])
        ->get(route('student.portal'))->assertOk()->assertSee('Vote now');

    $this->actingAs($student, 'student')->withSession(['student_last_activity' => time()])
        ->get(route('student.candidates', $s['election']))->assertOk()->assertSee('Alpha President')->assertSee('Fictional platform');
});

it('does not show vote totals on the dashboard while voting is open', function () {
    $s = votingElection();
    $this->actingAs(staff())->get('/admin?election='.$s['election']->id)
        ->assertOk()->assertSee('Turnout')->assertDontSee('Alpha President');
});

it('updates school settings and signatories', function () {
    Livewire::actingAs(staff())->test(Settings::class)
        ->set('name', 'Nancapian National High School')
        ->set('school_code', '300000')
        ->call('saveSchool')
        ->assertHasNoErrors()
        ->set('signatories.0.name', 'Juana Dela Cruz')
        ->call('saveSignatories')
        ->assertHasNoErrors();

    expect(school()->school_code)->toBe('300000')
        ->and(Setting::signatories()[0]['name'])->toBe('Juana Dela Cruz');
});

it('creates staff accounts with a strong password only', function () {
    Livewire::actingAs(staff())->test(Users::class)
        ->call('create')
        ->set('name', 'Committee Chair')
        ->set('email', 'chair@example.test')
        ->set('role', 'committee')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('save')
        ->assertHasErrors('password')
        ->set('password', 'Very-Strong-Pass-2026')
        ->set('password_confirmation', 'Very-Strong-Pass-2026')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::firstWhere('email', 'chair@example.test')->role())->toBe(Role::Committee);
});
