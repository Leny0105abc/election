<?php

namespace App\Livewire\Admin\Elections;

use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Concerns\RunsGuardedActions;
use App\Models\Election;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Party lists group candidates for display only. They never pre-fill or
 * restrict a student's ballot selections.
 */
#[Layout('components.layouts.admin')]
#[Title('Party lists')]
class PartyLists extends Component
{
    use RunsGuardedActions, WithFileUploads;

    public Election $election;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $acronym = '';

    public string $color = '#2563eb';

    public string $description = '';

    public $logo;

    public ?int $viewingId = null;

    public function mount(Election $election): void
    {
        $this->authorize('view-monitoring');
        $this->election = $election;
    }

    public function create(): void
    {
        $this->authorize('manage-elections');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'acronym', 'description', 'logo']);
        $this->color = '#2563eb';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-elections');
        $p = $this->election->partyLists()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->acronym = (string) $p->acronym;
        $this->color = $p->color;
        $this->description = (string) $p->description;
        $this->logo = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guarded('manage-elections', function () {
            if (! $this->election->fresh()->isEditable()) {
                throw new ElectionStateException('Party lists are locked once voting opens.');
            }

            $this->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('party_lists', 'name')->where('election_id', $this->election->id)->ignore($this->editingId)],
                'acronym' => ['nullable', 'string', 'max:20'],
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'description' => ['nullable', 'string', 'max:1000'],
                'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('schoolvote.max_image_kb')],
            ]);

            $data = [
                'name' => $this->name,
                'acronym' => $this->acronym ?: null,
                'color' => $this->color,
                'description' => $this->description ?: null,
            ];

            if ($this->logo) {
                $data['logo_path'] = $this->logo->store('party-logos', 'public');
            }

            $party = $this->editingId
                ? tap($this->election->partyLists()->findOrFail($this->editingId))->update($data)
                : $this->election->partyLists()->create($data);

            AuditLogger::log($this->editingId ? 'party_list.updated' : 'party_list.created', $party, ['name' => $party->name]);
            $this->showForm = false;
        }, 'Party list saved.');
    }

    public function delete(int $id): void
    {
        $this->guarded('manage-elections', function () use ($id) {
            if (! $this->election->fresh()->isEditable()) {
                throw new ElectionStateException('Party lists are locked once voting opens.');
            }
            $p = $this->election->partyLists()->findOrFail($id);
            AuditLogger::log('party_list.deleted', $p, ['name' => $p->name]);
            $p->delete(); // members become independent (nullOnDelete)
        }, 'Party list deleted. Its members are now independent.');
    }

    public function render()
    {
        return view('livewire.admin.elections.party-lists', [
            'parties' => $this->election->partyLists()->with(['candidates.student', 'candidates.position'])->get(),
            'independents' => $this->election->candidates()->whereNull('party_list_id')->with(['student', 'position'])->get(),
            'editable' => $this->election->isEditable(),
        ]);
    }
}
