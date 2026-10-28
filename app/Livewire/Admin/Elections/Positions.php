<?php

namespace App\Livewire\Admin\Elections;

use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Position;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Positions')]
class Positions extends Component
{
    use RunsGuardedActions;

    public Election $election;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public int $display_order = 0;

    public int $max_winners = 1;

    /** @var list<int|string> */
    public array $voter_grades = [];

    /** @var list<int|string> */
    public array $candidate_grades = [];

    public bool $allow_abstain = true;

    public bool $is_active = true;

    public ?int $catalogPositionId = null;

    public function mount(Election $election): void
    {
        $this->authorize('view-monitoring');
        $this->election = $election;
    }

    private function assertEditable(): void
    {
        if (! $this->election->fresh()->isEditable()) {
            throw new ElectionStateException('Positions are locked once an election opens, so ballots cannot be invalidated.');
        }
    }

    public function create(): void
    {
        $this->authorize('manage-elections');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'description', 'voter_grades', 'candidate_grades']);
        $this->max_winners = 1;
        $this->allow_abstain = true;
        $this->is_active = true;
        $this->display_order = (int) $this->election->positions()->max('display_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-elections');
        $p = $this->election->positions()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->description = (string) $p->description;
        $this->display_order = $p->display_order;
        $this->max_winners = $p->max_winners;
        $this->voter_grades = $p->eligible_voter_grades ?? [];
        $this->candidate_grades = $p->eligible_candidate_grades ?? [];
        $this->allow_abstain = $p->allow_abstain;
        $this->is_active = $p->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guarded('manage-elections', function () {
            $this->assertEditable();

            $this->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('election_positions', 'name')
                    ->where('election_id', $this->election->id)->ignore($this->editingId)],
                'description' => ['nullable', 'string', 'max:1000'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'max_winners' => ['required', 'integer', 'min:1', 'max:20'],
                'voter_grades.*' => ['integer', Rule::in(config('schoolvote.grade_levels'))],
                'candidate_grades.*' => ['integer', Rule::in(config('schoolvote.grade_levels'))],
            ]);

            $data = [
                'name' => $this->name,
                'description' => $this->description ?: null,
                'display_order' => $this->display_order,
                'max_winners' => $this->max_winners,
                'eligible_voter_grades' => $this->grades($this->voter_grades),
                'eligible_candidate_grades' => $this->grades($this->candidate_grades),
                'allow_abstain' => $this->allow_abstain,
                'is_active' => $this->is_active,
            ];

            $position = $this->editingId
                ? tap($this->election->positions()->findOrFail($this->editingId))->update($data)
                : $this->election->positions()->create($data);

            AuditLogger::log($this->editingId ? 'position.updated' : 'position.created', $position, ['name' => $position->name]);
            $this->showForm = false;
        }, 'Position saved.');
    }

    /** @return list<int>|null */
    private function grades(array $grades): ?array
    {
        $grades = array_values(array_unique(array_map('intval', $grades)));
        sort($grades);

        return $grades ?: null;
    }

    public function addFromCatalog(): void
    {
        $this->guarded('manage-elections', function () {
            $this->assertEditable();
            $p = Position::findOrFail($this->catalogPositionId);

            if ($this->election->positions()->where('name', $p->name)->exists()) {
                throw new ElectionStateException("{$p->name} is already on this ballot.");
            }

            $created = $this->election->positions()->create($p->toElectionPositionAttributes());
            AuditLogger::log('position.created', $created, ['name' => $created->name]);
            $this->catalogPositionId = null;
        }, 'Position added.');
    }

    public function move(int $id, int $direction): void
    {
        $this->guarded('manage-elections', function () use ($id, $direction) {
            $this->assertEditable();
            $positions = $this->election->positions()->get()->values();
            $index = $positions->search(fn ($p) => $p->id === $id);
            $swap = $index + $direction;

            if ($index === false || ! isset($positions[$swap])) {
                return;
            }

            // Renumber so ordering is always explicit and stable.
            $ordered = $positions->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $i => $p) {
                ElectionPosition::whereKey($p->id)->update(['display_order' => $i + 1]);
            }
        });
    }

    public function delete(int $id): void
    {
        $this->guarded('manage-elections', function () use ($id) {
            $this->assertEditable();
            $p = $this->election->positions()->withCount('candidates')->findOrFail($id);

            if ($p->candidates_count > 0) {
                throw new ElectionStateException('Remove or move this position\'s candidates first, or mark the position inactive.');
            }

            AuditLogger::log('position.deleted', $p, ['name' => $p->name]);
            $p->delete();
        }, 'Position removed.');
    }

    public function render()
    {
        $this->election->refresh();

        return view('livewire.admin.elections.positions', [
            'positions' => $this->election->positions()->withCount(['candidates', 'approvedCandidates'])->get(),
            'catalog' => Position::where('is_active', true)->orderBy('display_order')->get(),
            'editable' => $this->election->isEditable(),
        ]);
    }
}
