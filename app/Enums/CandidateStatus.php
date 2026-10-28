<?php

namespace App\Enums;

enum CandidateStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-800 ring-amber-300',
            self::Approved => 'bg-emerald-50 text-emerald-700 ring-emerald-300',
            self::Rejected => 'bg-rose-50 text-rose-700 ring-rose-300',
            self::Withdrawn => 'bg-zinc-100 text-zinc-600 ring-zinc-300',
        };
    }
}
