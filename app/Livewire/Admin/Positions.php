<?php

namespace App\Livewire\Admin;

use App\Models\Position;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * School-wide position catalogue. New elections copy these positions;
 * editing the catalogue never changes an existing election.
 */
#[Layout('components.layouts.admin')]
#[Title('Position catalogue')]
class Positions extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public int $display_order = 0;

    public int $max_winners = 1;

    public array $voter_grades = [];

    public array $candidate_grades = [];

    public bool $allow_abstain = true;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('manage-elections');
    }

    public function create(): void
    {
        $this->authorize('manage-elections');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'description', 'voter_grades', 'candidate_grades']);
        $this->display_order = (int) Position::max('display_order') + 1;
        $this->max_winners = 1;
        $this->allow_abstain = true;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-elections');
        $p = Position::findOrFail($id);
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
        $this->authorize('manage-elections');
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('positions', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:999'],
            'max_winners' => ['required', 'integer', 'min:1', 'max:20'],
            'voter_grades.*' => ['integer', Rule::in(config('schoolvote.grade_levels'))],
            'candidate_grades.*' => ['integer', Rule::in(config('schoolvote.grade_levels'))],
        ]);

        $norm = function (array $g) {
            $g = array_values(array_unique(array_map('intval', $g)));
            sort($g);

            return $g ?: null;
        };

        $position = Position::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'display_order' => $this->display_order,
            'max_winners' => $this->max_winners,
            'eligible_voter_grades' => $norm($this->voter_grades),
            'eligible_candidate_grades' => $norm($this->candidate_grades),
            'allow_abstain' => $this->allow_abstain,
            'is_active' => $this->is_active,
        ]);

        AuditLogger::log('catalog_position.saved', $position, ['name' => $position->name]);
        $this->showForm = false;
        $this->dispatch('notify', message: 'Position saved. Existing elections are not changed.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.positions', [
            'positions' => Position::orderBy('display_order')->orderBy('name')->get(),
        ]);
    }
}
