<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the student import sheet as an array of rows keyed by heading
 * (headings are slugged: "Student ID" -> "student_id").
 */
class StudentRowsImport implements Import, WithHeadingRow {}
