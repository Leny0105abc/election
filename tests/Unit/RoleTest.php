<?php

use App\Enums\Role;

it('gives only the Super Administrator system-wide powers', function (string $permission) {
    expect(Role::SuperAdmin->can($permission))->toBeTrue()
        ->and(Role::ElectionAdmin->can($permission))->toBeFalse()
        ->and(Role::Committee->can($permission))->toBeFalse();
})->with(['manage-settings', 'manage-users', 'finalize-election', 'publish-results', 'reopen-election']);

it('lets election administrators run elections and manage students', function () {
    expect(Role::ElectionAdmin->can('manage-elections'))->toBeTrue()
        ->and(Role::ElectionAdmin->can('operate-elections'))->toBeTrue()
        ->and(Role::ElectionAdmin->can('manage-students'))->toBeTrue()
        ->and(Role::ElectionAdmin->can('view-audit'))->toBeFalse();
});

it('limits the committee to monitoring and review', function () {
    expect(Role::Committee->permissions())
        ->toEqualCanonicalizing(['view-monitoring', 'view-canvass', 'view-reports', 'view-audit']);
});

it('lists every permission once', function () {
    $all = Role::allPermissions();

    expect($all)->toBe(array_values(array_unique($all)))
        ->and($all)->toContain('manage-settings', 'view-monitoring', 'view-voter-reports');
});
