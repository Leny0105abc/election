<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function show(Request $request, Election $election, string $report, string $format, ReportService $reports)
    {
        $reason = $reports->unavailableReason($election, $report, $request->user());
        abort_if($reason !== null, 403, (string) $reason);

        $data = $reports->build($election, $report);
        $school = school();
        $filename = Str::slug($election->name.' '.$data['title']).'-'.now()->format('Ymd-His');

        AuditLogger::log('report.generated', $election, ['report' => $report, 'format' => $format]);

        $viewData = [
            'report' => $data,
            'election' => $election,
            'school' => $school,
            'signatories' => Setting::signatories(),
            'generatedAt' => local_dt(now()),
            'generatedBy' => $request->user()->name,
        ];

        return match ($format) {
            'pdf' => Pdf::loadView('reports.document', $viewData + ['mode' => 'pdf', 'logo' => $this->logoDataUri()])
                ->setPaper('a4', $data['landscape'] ? 'landscape' : 'portrait')
                ->download($filename.'.pdf'),
            'xlsx' => Excel::download(new ReportExport($data, [
                $school->name,
                $election->name.' — '.$data['title'],
                'School Year '.$election->schoolYear->name.' · Voting: '.local_dt($election->starts_at).' to '.local_dt($election->ends_at),
                'Generated '.local_dt(now()).' by '.$request->user()->name,
            ]), $filename.'.xlsx'),
            'print' => view('reports.document', $viewData + ['mode' => 'print', 'logo' => school()->logoUrl()]),
        };
    }

    private function logoDataUri(): ?string
    {
        $path = school()->logoFilePath();

        if (! $path) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
