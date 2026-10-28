<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes append-only audit entries.
 *
 * Rules: never pass ballot choices, PINs, passwords or tokens in $properties.
 */
class AuditLogger
{
    /** Property keys that must never be stored. */
    private const FORBIDDEN_KEYS = ['pin', 'pin_hash', 'password', 'token', 'selections', 'candidate_id', 'candidate_ids'];

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?string $description = null,
        ?int $electionId = null,
    ): ?AuditLog {
        try {
            [$actorType, $actor] = self::resolveActor();

            if ($electionId === null) {
                if ($subject instanceof Election) {
                    $electionId = $subject->id;
                } elseif ($subject && isset($subject->election_id)) {
                    $electionId = (int) $subject->election_id;
                }
            }

            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            return AuditLog::create([
                'actor_type' => $actorType,
                'actor_id' => $actor?->getKey(),
                'actor_name' => match (true) {
                    $actor instanceof User => $actor->name,
                    $actor instanceof Student => $actor->student_number,
                    default => $actorType === 'system' ? 'System' : null,
                },
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'election_id' => $electionId,
                'description' => $description ? Str::limit($description, 250) : null,
                'properties' => self::clean($properties) ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the main operation, but failures are reported.
            report($e);

            return null;
        }
    }

    /** @return array{0: string, 1: User|Student|null} */
    private static function resolveActor(): array
    {
        if ($user = Auth::guard('web')->user()) {
            return ['staff', $user];
        }

        if ($student = Auth::guard('student')->user()) {
            return ['student', $student];
        }

        return ['system', null];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function clean(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                unset($properties[$key]);
            } elseif (is_array($value)) {
                $properties[$key] = self::clean($value);
            }
        }

        return $properties;
    }
}
