<?php

namespace App\Enums;

/**
 * Staff roles. Student voters authenticate through the separate "student"
 * guard and never hold one of these roles.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case ElectionAdmin = 'election_admin';
    case Committee = 'committee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::ElectionAdmin => 'Election Administrator',
            self::Committee => 'Election Committee',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full system configuration, users, finalization, audit review and reports.',
            self::ElectionAdmin => 'Candidates, voters, election operations, monitoring and permitted reports.',
            self::Committee => 'Limited monitoring, canvass review and reports.',
        };
    }

    /**
     * Permissions granted to each role. Checked through Gates (see AppServiceProvider).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                'manage-settings', 'manage-users', 'manage-elections', 'operate-elections',
                'reopen-election', 'finalize-election', 'publish-results', 'manage-students',
                'view-monitoring', 'view-canvass', 'view-reports', 'view-voter-reports', 'view-audit',
            ],
            self::ElectionAdmin => [
                'manage-elections', 'operate-elections', 'manage-students',
                'view-monitoring', 'view-canvass', 'view-reports', 'view-voter-reports',
            ],
            self::Committee => [
                'view-monitoring', 'view-canvass', 'view-reports', 'view-audit',
            ],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<string> */
    public static function allPermissions(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (self $role) => $role->permissions(),
            self::cases(),
        ))));
    }
}
