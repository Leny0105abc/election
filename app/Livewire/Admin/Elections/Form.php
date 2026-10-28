<?php

namespace App\Livewire\Admin\Elections;

use App\Enums\ElectionType;
use App\Exceptions\ElectionStateException;
use App\Models\Election;
use App\Models\SchoolYear;
use App\Services\ElectionService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
#[Title('Election')]
class Form extends Component
{
    use WithFileUploads;

    public ?Election $election = null;

    public string $name = '';

    public string $type = 'sslg';

    public ?int $school_year_id = null;

    public string $description = '';

    /** Local (Asia/Manila) datetime-local strings */
    public string $starts_at = '';

    public string $ends_at = '';

    /** @var list<int|string> */
    public array $eligible_grades = [];

    public string $tie_resolution_rule = 'Ties affecting the winners are resolved by toss coin or drawing of lots, conducted by the Election Committee in the presence of the tied candidates.';

    public bool $copy_positions = true;

    public $banner;

    public function mount(?Election $election = null): void
    {
        if ($election?->exists) {
            $this->authorize('update', $election);
        } else {
            $this->authorize('create', Election::class);
        }

        if ($election?->exists) {
            $this->election = $election;
            $this->name = $election->name;
            $this->school_year_id = $election->school_year_id;
            $this->tie_resolution_rule = (string) $election->tie_resolution_rule;
            $this->type = $election->type->value;
            $this->description = (string) $election->description;
            $this->starts_at = local_dt($election->starts_at, 'Y-m-d\TH:i');
            $this->ends_at = local_dt($election->ends_at, 'Y-m-d\TH:i');
            $this->eligible_grades = array_map('intval', $election->eligible_grades ?? []);
        } else {
            $this->school_year_id = SchoolYear::current()?->id;
            $this->eligible_grades = config('schoolvote.grade_levels');
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ElectionType::class)],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'eligible_grades' => ['required', 'array', 'min:1'],
            'eligible_grades.*' => ['integer', Rule::in(config('schoolvote.grade_levels'))],
            'tie_resolution_rule' => ['nullable', 'string', 'max:2000'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('schoolvote.max_image_kb')],
        ];
    }

    protected $messages = [
        'ends_at.after' => 'Voting must end after it starts.',
        'eligible_grades.required' => 'Select at least one grade level.',
    ];

    public function save(ElectionService $service)
    {
        $this->election
            ? $this->authorize('update', $this->election)
            : $this->authorize('create', Election::class);
        $this->validate();

        $tz = config('schoolvote.display_timezone');
        $grades = array_values(array_map('intval', $this->eligible_grades));
        sort($grades);

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'school_year_id' => $this->school_year_id,
            'description' => $this->description ?: null,
            'starts_at' => $this->starts_at ? CarbonImmutable::parse($this->starts_at, $tz)->utc() : null,
            'ends_at' => $this->ends_at ? CarbonImmutable::parse($this->ends_at, $tz)->utc() : null,
            'eligible_grades' => $grades,
            'tie_resolution_rule' => $this->tie_resolution_rule ?: null,
        ];

        if ($this->banner) {
            $data['banner_path'] = $this->banner->store('banners', 'public');
        }

        try {
            $election = $this->election
                ? $service->update($this->election, $data)
                : $service->create($data, auth()->user(), $this->copy_positions);
        } catch (ElectionStateException $e) {
            $this->addError('starts_at', $e->getMessage());

            return null;
        }

        session()->flash('success', 'Election saved.');

        return $this->redirectRoute('admin.elections.show', $election, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.elections.form', [
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'types' => ElectionType::cases(),
            'locked' => $this->election && ! $this->election->isEditable(),
        ]);
    }
}
