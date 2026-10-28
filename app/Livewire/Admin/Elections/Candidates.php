<?php

namespace App\Livewire\Admin\Elections;

use App\Enums\CandidateStatus;
use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\CandidateEligibilityService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
#[Title('Candidates')]
class Candidates extends Component
{
    use RunsGuardedActions, WithFileUploads;

    public Election $election;

    #[Url]
    public string $positionFilter = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $studentSearch = '';

    public ?int $student_id = null;

    public ?int $election_position_id = null;

    public ?int $party_list_id = null;

    public string $ballot_name = '';

    public string $platform = '';

    public string $biography = '';

    public $photo;

    public bool $showReject = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function mount(Election $election): void
    {
        $this->authorize('view-monitoring');
        $this->election = $election;
    }

    private function assertEditable(): void
    {
        if (! $this->election->fresh()->isEditable()) {
            throw new ElectionStateException('Candidates are locked once voting opens, so every ballot shows the same choices.');
        }
    }

    public function create(): void
    {
        $this->authorize('manage-elections');
        $this->resetValidation();
        $this->reset(['editingId', 'studentSearch', 'student_id', 'party_list_id', 'ballot_name', 'platform', 'biography', 'photo']);
        $this->election_position_id = $this->positionFilter !== '' ? (int) $this->positionFilter : null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-elections');
        $c = $this->election->candidates()->with('student')->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $c->id;
        $this->student_id = $c->student_id;
        $this->studentSearch = '';
        $this->election_position_id = $c->election_position_id;
        $this->party_list_id = $c->party_list_id;
        $this->ballot_name = (string) $c->ballot_name;
        $this->platform = (string) $c->platform;
        $this->biography = (string) $c->biography;
        $this->photo = null;
        $this->showForm = true;
    }

    public function pickStudent(int $id): void
    {
        $this->student_id = $id;
        $this->studentSearch = '';
    }

    public function save(CandidateEligibilityService $eligibility): void
    {
        $this->guarded('manage-elections', function () use ($eligibility) {
            $this->assertEditable();

            $this->validate([
                'student_id' => ['required', 'exists:students,id'],
                'election_position_id' => ['required', 'exists:election_positions,id'],
                'party_list_id' => ['nullable', 'exists:party_lists,id'],
                'ballot_name' => ['nullable', 'string', 'max:150'],
                'platform' => ['nullable', 'string', 'max:3000'],
                'biography' => ['nullable', 'string', 'max:3000'],
                'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('schoolvote.max_image_kb'), 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'],
            ], ['student_id.required' => 'Choose the student who is running.']);

            $position = ElectionPosition::where('election_id', $this->election->id)->findOrFail($this->election_position_id);
            if ($this->party_list_id) {
                $this->election->partyLists()->findOrFail($this->party_list_id);
            }

            $student = Student::findOrFail($this->student_id);
            $existing = $this->editingId ? $this->election->candidates()->findOrFail($this->editingId) : null;
            $problems = $eligibility->problems($student, $position, $existing);

            if ($problems) {
                $this->addError('student_id', implode(' ', $problems));

                return;
            }

            $enrollment = $eligibility->enrollmentFor($student, $this->election);

            $data = [
                'election_position_id' => $position->id,
                'student_id' => $student->id,
                'party_list_id' => $this->party_list_id ?: null,
                'ballot_name' => $this->ballot_name ?: null,
                'grade_level' => $enrollment->grade_level,
                'platform' => $this->platform ?: null,
                'biography' => $this->biography ?: null,
            ];

            if ($this->photo) {
                $data['photo_path'] = $this->photo->store('candidates', 'public');
            }

            if ($existing) {
                $existing->update($data);
                $candidate = $existing;
            } else {
                $candidate = $this->election->candidates()->create($data + ['status' => CandidateStatus::Pending]);
            }

            AuditLogger::log($existing ? 'candidate.updated' : 'candidate.registered', $candidate, ['position' => $position->name]);
            $this->showForm = false;
            $this->dispatch('notify', message: 'Candidate saved.', type: 'success');
        });
    }

    public function approve(int $id, CandidateEligibilityService $eligibility): void
    {
        $this->guarded('manage-elections', function () use ($id, $eligibility) {
            $this->assertEditable();
            $c = $this->election->candidates()->with(['student', 'position.election.schoolYear'])->findOrFail($id);
            $problems = $eligibility->problems($c->student, $c->position, $c);
            if ($problems) {
                throw new ElectionStateException('Cannot approve: '.implode(' ', $problems));
            }
            $c->update(['status' => CandidateStatus::Approved, 'status_reason' => null]);
            AuditLogger::log('candidate.approved', $c);
        }, 'Candidate approved.');
    }

    public function openReject(int $id): void
    {
        $this->authorize('manage-elections');
        $this->rejectingId = $id;
        $this->rejectReason = '';
        $this->showReject = true;
    }

    public function reject(): void
    {
        $this->validate(['rejectReason' => 'required|string|min:3|max:255']);
        $this->guarded('manage-elections', function () {
            $this->assertEditable();
            $c = $this->election->candidates()->findOrFail($this->rejectingId);
            $c->update(['status' => CandidateStatus::Rejected, 'status_reason' => $this->rejectReason]);
            AuditLogger::log('candidate.rejected', $c, ['reason' => $this->rejectReason]);
            $this->showReject = false;
        }, 'Candidate rejected.');
    }

    public function withdraw(int $id): void
    {
        $this->guarded('manage-elections', function () use ($id) {
            $this->assertEditable();
            $c = $this->election->candidates()->findOrFail($id);
            $c->update(['status' => CandidateStatus::Withdrawn, 'status_reason' => 'Withdrawn before voting opened']);
            AuditLogger::log('candidate.withdrawn', $c);
        }, 'Candidate withdrawn.');
    }

    public function delete(int $id): void
    {
        $this->guarded('manage-elections', function () use ($id) {
            $this->assertEditable();
            $c = $this->election->candidates()->findOrFail($id);
            AuditLogger::log('candidate.deleted', $c, ['student_id' => $c->student_id]);
            $c->delete();
        }, 'Candidate removed.');
    }

    public function render()
    {
        $candidates = $this->election->candidates()
            ->with(['student', 'position', 'partyList'])
            ->when($this->positionFilter !== '', fn ($q) => $q->where('election_position_id', $this->positionFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->get()
            ->sortBy([fn ($a, $b) => $a->position->display_order <=> $b->position->display_order, fn ($a, $b) => strcmp($a->display_name, $b->display_name)]);

        $searchResults = strlen(trim($this->studentSearch)) >= 2
            ? Student::search($this->studentSearch)
                ->with(['enrollments' => fn ($q) => $q->where('school_year_id', $this->election->school_year_id)])
                ->orderBy('last_name')->limit(8)->get()
            : collect();

        return view('livewire.admin.elections.candidates', [
            'candidates' => $candidates,
            'positions' => $this->election->positions()->get(),
            'parties' => $this->election->partyLists()->get(),
            'statuses' => CandidateStatus::cases(),
            'searchResults' => $searchResults,
            'selectedStudent' => $this->student_id ? Student::find($this->student_id) : null,
            'editable' => $this->election->isEditable(),
        ]);
    }
}
