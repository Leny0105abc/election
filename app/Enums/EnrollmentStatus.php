<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case Transferred = 'transferred';
    case Dropped = 'dropped';
    case Graduated = 'graduated';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
