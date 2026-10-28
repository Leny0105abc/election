<?php

namespace App\Livewire\Admin\Students;

use App\Enums\EnrollmentStatus;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\PinService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Bulk PIN generation. The plain PINs exist only inside the PDF of
 * printable slips returned by this request; only hashes are stored.
 */
#[Layout('components.layouts.admin')]
#[Title('Generate PINs')]
class Pins extends Component
{
    public ?int $schoolYearId = null;

    public string $grade = '';

    public string $section = '';

    /** "missing" = only students without a PIN; "all" = replace every PIN in scope */
    public string $mode = 'missing';

    public function mount(): void
    {
        $this->authorize('manage-students');
        $this->schoolYearId = SchoolYear::current()?->id;
    }

    /** @return Builder<Student> */
    private function scope(): Builder
    {
        return Student::query()
            ->where('is_active', true)
            ->when($this->mode === 'missing', fn ($q) => $q->whereNull('pin_hash'))
            ->whereHas('enrollments', function ($q) {
                $q->where('school_year_id', $this->schoolYearId)
                    ->where('enrollment_status', EnrollmentStatus::Enrolled)
                    ->when($this->grade !== '', fn ($x) => $x->where('grade_level', $this->grade))
                    ->when($this->section !== '', fn ($x) => $x->where('section', $this->section));
            });
    }

    public function generate(PinService $pins)
    {
        $this->authorize('manage-students');
        $this->validate([
            'schoolYearId' => 'required|exists:school_years,id',
            'mode' => 'required|in:missing,all',
        ]);

        $students = $this->scope()
            ->with(['enrollments' => fn ($q) => $q->where('school_year_id', $this->schoolYearId)])
            ->get()
            ->sortBy(fn ($s) => sprintf('%02d|%s|%s|%s', $s->enrollments->first()?->grade_level, $s->enrollments->first()?->section, $s->last_name, $s->first_name))
            ->values();

        if ($students->isEmpty()) {
            $this->dispatch('notify', message: 'No students match. Everyone in this group may already have a PIN.', type: 'warning');

            return null;
        }

        if ($students->count() > 600) {
            $this->dispatch('notify', message: 'Generate PINs per grade level (max 600 at a time) to stay within hosting time limits.', type: 'warning');

            return null;
        }

        $issued = $pins->assign($students);

        $pdf = Pdf::loadView('reports.pin-slips', [
            'issued' => $issued,
            'school' => school(),
            'schoolYearId' => $this->schoolYearId,
            'loginUrl' => route('student.login'),
            'generatedAt' => local_dt(now()),
        ])->setPaper('a4');

        $output = $pdf->output();
        unset($issued);

        $this->dispatch('notify', message: 'PINs generated. Keep the downloaded slips secure and destroy extra copies.', type: 'success');

        return response()->streamDownload(fn () => print ($output), 'pin-slips-'.now()->format('Ymd-His').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function render()
    {
        return view('livewire.admin.students.pins', [
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'sections' => StudentEnrollment::where('school_year_id', $this->schoolYearId)
                ->when($this->grade !== '', fn ($q) => $q->where('grade_level', $this->grade))
                ->distinct()->orderBy('section')->pluck('section'),
            'count' => $this->schoolYearId ? $this->scope()->count() : 0,
        ]);
    }
}
