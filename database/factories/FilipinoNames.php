<?php

namespace Database\Factories;

/**
 * Common Filipino names for realistic FICTIONAL seed data.
 * Combinations are random; any resemblance to real students is coincidental.
 */
final class FilipinoNames
{
    public const FIRST = [
        'Juan', 'Jose', 'Mark', 'John Paul', 'Christian', 'Angelo', 'Carlo', 'Miguel', 'Rafael', 'Joshua',
        'Kyle', 'Ian', 'Paolo', 'Gabriel', 'Nathaniel', 'Jericho', 'Renz', 'Aldrin', 'Vincent', 'Jomar',
        'Maria', 'Angelica', 'Kristine', 'Princess', 'Jasmine', 'Nicole', 'Andrea', 'Bea', 'Camille', 'Patricia',
        'Rhea', 'Shaira', 'Joanna', 'Mika', 'Althea', 'Sofia', 'Janelle', 'Trisha', 'Kimberly', 'Hannah',
    ];

    public const LAST = [
        'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos', 'Aquino', 'Castillo',
        'Torres', 'Flores', 'Gonzales', 'Rivera', 'Navarro', 'Fernandez', 'Lopez', 'Mercado', 'Pascual', 'Soriano',
        'Domingo', 'Salazar', 'Valdez', 'Gutierrez', 'Cruz', 'Manalo', 'Agustin', 'Tolentino', 'Ocampo', 'Panganiban',
        'Lacson', 'Dizon', 'Javier', 'Morales', 'Aguilar', 'Del Rosario', 'Samonte', 'Cabrera', 'Evangelista', 'Medina',
    ];

    /** Section names per grade (DepEd style). */
    public const SECTIONS = [
        7 => ['Sampaguita', 'Rosal', 'Ilang-Ilang'],
        8 => ['Narra', 'Molave', 'Acacia'],
        9 => ['Rizal', 'Bonifacio', 'Mabini'],
        10 => ['Diamond', 'Emerald', 'Ruby'],
        11 => ['STEM-A', 'ABM-A', 'HUMSS-A'],
        12 => ['STEM-B', 'ABM-B', 'HUMSS-B'],
    ];

    public static function first(): string
    {
        return self::FIRST[array_rand(self::FIRST)];
    }

    public static function last(): string
    {
        return self::LAST[array_rand(self::LAST)];
    }
}
