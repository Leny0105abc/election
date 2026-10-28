<?php

namespace App\Enums;

enum ElectionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Finalized = 'finalized';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Tailwind classes for status badges. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::Scheduled => 'bg-sky-50 text-sky-700 ring-sky-300',
            self::Open => 'bg-emerald-50 text-emerald-700 ring-emerald-300',
            self::Closed => 'bg-amber-50 text-amber-800 ring-amber-300',
            self::Finalized => 'bg-indigo-50 text-indigo-700 ring-indigo-300',
            self::Archived => 'bg-zinc-100 text-zinc-600 ring-zinc-300',
        };
    }

    /**
     * Allowed status changes through the normal workflow.
     * Reopening a closed election and un-finalizing are handled separately.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled],
            self::Scheduled => [self::Draft, self::Open],
            self::Open => [self::Closed],
            self::Closed => [self::Finalized],
            self::Finalized => [self::Archived],
            self::Archived => [],
        };
    }

    /** Ballot structure (positions, candidates, rules) may still change. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /** Vote totals may be shown to authorised staff. */
    public function allowsCanvass(): bool
    {
        return in_array($this, [self::Closed, self::Finalized, self::Archived], true);
    }
}
