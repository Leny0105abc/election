<?php

namespace App\Enums;

enum ElectionType: string
{
    case Sslg = 'sslg';
    case Classroom = 'classroom';
    case Club = 'club';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sslg => 'SSLG Election',
            self::Classroom => 'Classroom Officers Election',
            self::Club => 'Club Election',
            self::Other => 'Other School Election',
        };
    }
}
