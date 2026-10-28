<?php

use App\Models\School;
use Carbon\CarbonInterface;

if (! function_exists('local_dt')) {
    /**
     * Format a (UTC) timestamp in the display timezone (Asia/Manila by default).
     */
    function local_dt(?CarbonInterface $value, string $format = 'M j, Y g:i A'): string
    {
        if ($value === null) {
            return '';
        }

        return $value->copy()->setTimezone(config('schoolvote.display_timezone'))->format($format);
    }
}

if (! function_exists('school')) {
    function school(): School
    {
        return School::current();
    }
}

if (! function_exists('grade_label')) {
    function grade_label(int|string|null $grade): string
    {
        return $grade === null || $grade === '' ? '' : 'Grade '.$grade;
    }
}
