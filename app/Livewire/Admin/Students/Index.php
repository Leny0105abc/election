<?php

namespace App\Livewire\Admin\Students;

use App\Enums\EnrollmentStatus;
use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Candidate;
use App\Models\ElectionVoter;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\AuditLogger;
use App\Services\PinService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Students')]
class Index extends Component
{
    use RunsGuardedActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $grade = '';

    #[Url]
    public string $section = '';

    #[Url]
    public string $eligibility = '';

    public ?int $schoolYearId = null;

    // Form state
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $student_number = '';

    public string $lrn = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public ?int $grade_level = 7;

    public string $student_section = '';

    public string $enrollment_status = 'enrolled';

    public bool $is_eligible = true;

    public bool $is_active = true;

    // One-time PIN display
    public bool $showPin = false;

    public string $issuedPin = '';

    public string $issuedFor = '';

    public function mount(): void
    {
        $this->authorize('manage-students');
        $this->schoolYearId = SchoolYear::current()?->id ?? SchoolYear::max('id');
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'grade', 'section', 'eligibility', 'schoolYearId'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('manage-students');
        $this->resetValidation();
        $this->reset(['editingId', 'student_number', 'lrn', 'first_name', 'middle_name', 'last_name', 'suffix', 'student_section']);
        $this->grade_level = 7;
        $this->enrollment_status = 'enrolled';
        $this->is_eligible = true;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-students');
        $s = Student::findOrFail($id);
        $e = StudentEnrollment::where('student_id', $id)->where('school_year_id', $this->schoolYearId)->first();
        $this->resetValidation();
        $this->editingId = $s->id;
        $this->student_number = $s->student_number;
        $this->lrn = (string) $s->lrn;
        $this->first_name = $s->first_name;
        $this->middle_name = (string) $s->middle_name;
        $this->last_name = $s->last_name;
        $this->suffix = (string) $s->suffix;
        $this->is_active = $s->is_active;
        $this->grade_level = $e?->grade_level ?? 7;
        $this->student_section = (string) $e?->section;
        $this->enrollment_status = $e?->enrollment_status->value ?? 'enrolled';
        $this->is_eligible = $e?->is_eligible ?? true;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('manage-students');

        $this->validate([
            'student_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('students', 'student_number')->ignore($this->editingId)],
            'lrn' => ['nullable', 'digits:12', Rule::unique('students', 'lrn')->ignore($this->editingId)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'grade_level' => ['required', 'integer', Rule::in(config('schoolvote.grade_levels'))],
            'student_section' => ['required', 'string', 'max:60'],
            'enrollment_status' => ['required', Rule::enum(EnrollmentStatus::class)],
            'schoolYearId' => ['required', 'exists:school_years,id'],
        ], [], ['student_section' => 'section', 'lrn' => 'LRN', 'student_number' => 'Student ID']);

        DB::transaction(function () {
            $student = $this->editingId ? Student::findOrFail($this->editingId) : new Student(['school_id' => school()->id]);
            $student->fill([
                'student_number' => $this->student_number,
                'lrn' => $this->lrn ?: null,
                'first_name' => $this->first_name,
                'middle_name' => $this->middle_name ?: null,
                'last_name' => $this->last_name,
                'suffix' => $this->suffix ?: null,
                'is_active' => $this->is_active,
            ])->save();

            StudentEnrollment::updateOrCreate(
                ['student_id' => $student->id, 'school_year_id' => $this->schoolYearId],
                ['grade_level' => $this->grade_level, 'section' => $this->student_section, 'enrollment_status' => $this->enrollment_status, 'is_eligible' => $this->is_eligible],
            );

            AuditLogger::log($this->editingId ? 'student.updated' : 'student.created', $student);
        });

        $this->showForm = false;
        $this->dispatch('notify', message: 'Student saved. Voter rolls of scheduled/open elections update when refreshed; completed ballots are never affected.', type: 'success');
    }

    public function toggleEligibility(int $studentId): void
    {
        $this->guarded('manage-students', function () use ($studentId) {
            $e = StudentEnrollment::where('student_id', $studentId)->where('school_year_id', $this->schoolYearId)->firstOrFail();
            $e->update(['is_eligible' => ! $e->is_eligible]);
            AuditLogger::log('student.eligibility_changed', $e->student, ['eligible' => $e->is_eligible]);
        }, 'Eligibility updated.');
    }

    public function resetPin(int $studentId, PinService $pins): void
    {
        $this->authorize('manage-students');
        $student = Student::findOrFail($studentId);
        $issued = $pins->assign(collect([$student]));
        AuditLogger::log('student.pin_reset', $student);

        // Shown once in this modal; never stored in plain text.
        $this->issuedPin = $issued[0]['pin'];
        $this->issuedFor = $student->full_name.' ('.$student->student_number.')';
        $this->showPin = true;
    }

    public function closePin(): void
    {
        $this->issuedPin = '';
        $this->issuedFor = '';
        $this->showPin = false;
    }

    public function delete(int $id): void
    {
        $this->guarded('manage-students', function () use ($id) {
            $student = Student::findOrFail($id);

            if (ElectionVoter::where('student_id', $id)->exists() || Candidate::where('student_id', $id)->exists()) {
                throw new ElectionStateException('This student is on an election voter roll or candidate list. Deactivate the account instead.');
            }

            AuditLogger::log('student.deleted', $student, ['student_number' => $student->student_number]);
            $student->delete();
        }, 'Student record deleted.');
    }

    public function render()
    {
        $students = Student::query()
            ->search($this->search)
            ->with(['enrollments' => fn ($q) => $q->where('school_year_id', $this->schoolYearId)])
            ->when($this->grade !== '' || $this->section !== '' || $this->eligibility !== '', function ($q) {
                $q->whereHas('enrollments', function ($e) {
                    $e->where('school_year_id', $this->schoolYearId)
                        ->when($this->grade !== '', fn ($x) => $x->where('grade_level', $this->grade))
                        ->when($this->section !== '', fn ($x) => $x->where('section', $this->section))
                        ->when($this->eligibility !== '', fn ($x) => $x->where('is_eligible', $this->eligibility === '1'));
                });
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25);

        return view('livewire.admin.students.index', [
            'students' => $students,
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'sections' => StudentEnrollment::where('school_year_id', $this->schoolYearId)
                ->when($this->grade !== '', fn ($q) => $q->where('grade_level', $this->grade))
                ->distinct()->orderBy('section')->pluck('section'),
            'statuses' => EnrollmentStatus::cases(),
        ]);
    }
}
