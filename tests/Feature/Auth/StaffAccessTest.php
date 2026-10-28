<?php

use App\Enums\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Auth;

it('signs staff in through the staff login', function () {
    $user = staff(Role::ElectionAdmin);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::TEST_PASSWORD])
        ->assertRedirect('/admin');

    expect(Auth::guard('web')->id())->toBe($user->id);
    $this->get('/admin')->assertOk()->assertSee('Dashboard');
});

it('rejects a wrong staff password', function () {
    $user = staff();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('refuses inactive staff accounts', function () {
    $user = User::factory()->inactive()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::TEST_PASSWORD])
        ->assertSessionHasErrors('email');
});

it('redirects guests from admin pages to the staff login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/admin/students')->assertRedirect(route('login'));
});

it('does not let students into the admin area', function () {
    $this->actingAs(makeStudent(), 'student')->get('/admin')->assertRedirect(route('login'));
});

it('does not let staff into the student voting area', function () {
    $this->actingAs(staff())->get(route('student.portal'))->assertRedirect(route('student.login'));
});

it('enforces role permissions on admin pages', function (Role $role, string $uri, int $status) {
    $this->actingAs(staff($role))->get($uri)->assertStatus($status);
})->with([
    'committee: students' => [Role::Committee, '/admin/students', 403],
    'committee: users' => [Role::Committee, '/admin/users', 403],
    'committee: settings' => [Role::Committee, '/admin/settings', 403],
    'committee: audit' => [Role::Committee, '/admin/audit-logs', 200],
    'committee: dashboard' => [Role::Committee, '/admin', 200],
    'admin: users' => [Role::ElectionAdmin, '/admin/users', 403],
    'admin: settings' => [Role::ElectionAdmin, '/admin/settings', 403],
    'admin: audit' => [Role::ElectionAdmin, '/admin/audit-logs', 403],
    'admin: students' => [Role::ElectionAdmin, '/admin/students', 200],
    'super: users' => [Role::SuperAdmin, '/admin/users', 200],
    'super: settings' => [Role::SuperAdmin, '/admin/settings', 200],
]);

it('requires two-factor setup when the school enforces it', function () {
    config(['schoolvote.require_staff_two_factor' => true]);
    $user = staff();

    $this->actingAs($user)->get('/admin/students')->assertRedirect(route('admin.account'));
    $this->actingAs($user)->get('/admin/account')->assertOk();

    $withTwoFactor = User::factory()->superAdmin()->withTwoFactor()->create();
    $this->actingAs($withTwoFactor)->get('/admin/students')->assertOk();
});

it('uses a strong password policy for staff', function () {
    $validator = validator(['password' => 'password123'], ['password' => [User::passwordRule()]]);
    expect($validator->fails())->toBeTrue();

    $validator = validator(['password' => 'Str0ng!Passw0rd'], ['password' => [User::passwordRule()]]);
    expect($validator->fails())->toBeFalse();
});

it('has no public staff registration', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/staff/register')->assertNotFound();
});
