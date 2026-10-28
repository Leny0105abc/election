<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Exceptions\ElectionStateException;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates elections and moves them through their lifecycle:
 * Draft -> Scheduled -> Open -> Closed -> Finalized -> Archived.
 */
class ElectionService
{
    public function __construct(private readonly VoterEligibilityService $eligibility) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, bool $copyDefaultPositions = true): Election
    {
        return DB::transaction(function () use ($data, $user, $copyDefaultPositions) {
            $election = new Election($data);
            $election->created_by = $user->id;
            $election->status = ElectionStatus::Draft;
            $election->save();

            if ($copyDefaultPositions) {
                Position::where('is_active', true)->orderBy('display_order')->get()
                    ->each(fn (Position $p) => $election->positions()->create($p->toElectionPositionAttributes()));
            }

            AuditLogger::log('election.created', $election, ['name' => $election->name]);

            return $election;
        });
    }

    /**
     * Update election details. Structural fields are locked once voting can start.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Election $election, array $data): Election
    {
        if (! $election->isEditable()) {
            $locked = ['school_year_id', 'starts_at', 'ends_at', 'eligible_grades', 'type'];
            foreach ($locked as $field) {
                if (array_key_exists($field, $data) && $this->differs($election, $field, $data[$field])) {
                    throw new ElectionStateException('Schedule, school year and eligible grades cannot change after an election opens.');
                }
            }
        }

        $election->fill($data)->save();
        AuditLogger::log('election.updated', $election, ['fields' => array_keys($election->getChanges())]);

        return $election;
    }

    private function differs(Election $election, string $field, mixed $value): bool
    {
        $current = $election->{$field};

        if ($current instanceof \DateTimeInterface) {
            return $value === null || CarbonImmutable::parse($value)->notEqualTo($current);
        }

        if (is_array($current) || is_array($value)) {
            $a = array_map('intval', (array) $current);
            $b = array_map('intval', (array) $value);
            sort($a);
            sort($b);

            return $a !== $b;
        }

        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }

        return (string) $current !== (string) $value;
    }

    /**
     * Problems that block scheduling/opening (empty when ready).
     *
     * @return list<string>
     */
    public function readinessProblems(Election $election): array
    {
        $problems = [];

        if (! $election->starts_at || ! $election->ends_at) {
            $problems[] = 'Set the voting start and end date/time.';
        } elseif ($election->ends_at->lessThanOrEqualTo($election->starts_at)) {
            $problems[] = 'The voting end time must be after the start time.';
        }

        $positionsWithCandidates = $election->positions()
            ->where('is_active', true)
            ->whereHas('candidates', fn ($q) => $q->where('status', CandidateStatus::Approved))
            ->count();

        if ($positionsWithCandidates === 0) {
            $problems[] = 'At least one active position needs an approved candidate.';
        }

        if ($this->eligibility->eligibleEnrollments($election)->count() === 0) {
            $problems[] = 'No enrolled, eligible students match this election\'s grade levels.';
        }

        return $problems;
    }

    public function schedule(Election $election): void
    {
        $this->assertStatus($election, ElectionStatus::Draft);
        $problems = $this->readinessProblems($election);
        if ($problems) {
            throw new ElectionStateException(implode(' ', $problems));
        }

        DB::transaction(function () use ($election) {
            $roll = $this->eligibility->syncVoterRoll($election);
            $election->status = ElectionStatus::Scheduled;
            $election->save();
            AuditLogger::log('election.scheduled', $election, ['voters' => $roll['total']]);
        });
    }

    public function unschedule(Election $election): void
    {
        $this->assertStatus($election, ElectionStatus::Scheduled);
        $election->status = ElectionStatus::Draft;
        $election->save();
        AuditLogger::log('election.unscheduled', $election);
    }

    public function open(Election $election, bool $automatic = false): void
    {
        $this->assertStatus($election, ElectionStatus::Scheduled);
        $problems = $this->readinessProblems($election);
        if ($problems) {
            throw new ElectionStateException(implode(' ', $problems));
        }

        if ($election->ends_at->isPast()) {
            throw new ElectionStateException('The voting end time has already passed. Move the election back to Draft and update the schedule.');
        }

        DB::transaction(function () use ($election, $automatic) {
            $roll = $this->eligibility->syncVoterRoll($election);
            $election->status = ElectionStatus::Open;
            $election->opened_at = CarbonImmutable::now();
            $election->save();
            AuditLogger::log('election.opened', $election, ['voters' => $roll['total'], 'automatic' => $automatic]);
        });
    }

    public function close(Election $election, bool $automatic = false): void
    {
        $this->assertStatus($election, ElectionStatus::Open);

        DB::transaction(function () use ($election, $automatic) {
            // Exclusive lock waits for in-flight ballots (which hold shared locks) to finish.
            Election::whereKey($election->id)->lockForUpdate()->first();
            $election->status = ElectionStatus::Closed;
            $election->closed_at = CarbonImmutable::now();
            $election->save();
            AuditLogger::log('election.closed', $election, ['automatic' => $automatic]);
        });
    }

    /**
     * Reopen a CLOSED (never a finalized) election. Super Administrator only,
     * with a documented reason and a new end time in the future.
     */
    public function reopen(Election $election, CarbonImmutable $newEndsAt, string $reason): void
    {
        $this->assertStatus($election, ElectionStatus::Closed);

        if (trim($reason) === '') {
            throw new ElectionStateException('A reason is required to reopen an election.');
        }

        if ($newEndsAt->isPast()) {
            throw new ElectionStateException('The new end time must be in the future.');
        }

        $previousEnd = $election->ends_at;
        $election->ends_at = $newEndsAt;
        $election->status = ElectionStatus::Open;
        $election->closed_at = null;
        $election->save();

        AuditLogger::log('election.reopened', $election, [
            'reason' => $reason,
            'previous_end' => $previousEnd?->toIso8601String(),
            'new_end' => $newEndsAt->toIso8601String(),
        ], 'Election reopened: '.$reason);
    }

    /** Extend the voting window of an OPEN election (e.g. power outage). */
    public function extend(Election $election, CarbonImmutable $newEndsAt, string $reason): void
    {
        $this->assertStatus($election, ElectionStatus::Open);

        if ($newEndsAt->lessThanOrEqualTo($election->ends_at)) {
            throw new ElectionStateException('The new end time must be later than the current end time.');
        }

        $previousEnd = $election->ends_at;
        $election->ends_at = $newEndsAt;
        $election->save();

        AuditLogger::log('election.extended', $election, [
            'reason' => $reason,
            'previous_end' => $previousEnd?->toIso8601String(),
            'new_end' => $newEndsAt->toIso8601String(),
        ], 'Voting extended: '.$reason);
    }

    public function archive(Election $election): void
    {
        $this->assertStatus($election, ElectionStatus::Finalized);
        $election->status = ElectionStatus::Archived;
        $election->save();
        AuditLogger::log('election.archived', $election);
    }

    /**
     * Called every minute by the scheduler: opens scheduled elections when
     * their start time arrives and closes open ones when their end time passes.
     *
     * @return array{opened: int, closed: int}
     */
    public function syncStatuses(): array
    {
        $opened = 0;
        $closed = 0;
        $now = CarbonImmutable::now();

        Election::where('status', ElectionStatus::Open)->where('ends_at', '<=', $now)->get()
            ->each(function (Election $e) use (&$closed) {
                $this->close($e, automatic: true);
                $closed++;
            });

        Election::where('status', ElectionStatus::Scheduled)
            ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->get()
            ->each(function (Election $e) use (&$opened) {
                try {
                    $this->open($e, automatic: true);
                    $opened++;
                } catch (ElectionStateException $ex) {
                    AuditLogger::log('election.auto_open_failed', $e, [], $ex->getMessage());
                }
            });

        return ['opened' => $opened, 'closed' => $closed];
    }

    private function assertStatus(Election $election, ElectionStatus $expected): void
    {
        if ($election->status !== $expected) {
            throw new ElectionStateException(
                "This action needs the election to be {$expected->label()}, but it is {$election->status->label()}."
            );
        }
    }
}
