<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Services\StudentImportService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(
            new ArrayExport(StudentImportService::HEADINGS, StudentImportService::templateRows(), 'Students'),
            'schoolvote-student-import-template.xlsx',
        );
    }
}
