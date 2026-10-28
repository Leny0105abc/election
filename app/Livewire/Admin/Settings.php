<?php

namespace App\Livewire\Admin;

use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Setting;
use App\Services\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
#[Title('School settings')]
class Settings extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $school_code = '';

    public string $address = '';

    public string $division = '';

    public string $region = '';

    public string $primary_color = '#1d4ed8';

    public string $secondary_color = '#facc15';

    public $logo;

    /** @var list<array{label: string, name: string, title: string}> */
    public array $signatories = [];

    public string $newSchoolYear = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
        $school = School::current();
        $this->name = $school->name;
        $this->school_code = (string) $school->school_code;
        $this->address = (string) $school->address;
        $this->division = (string) $school->division;
        $this->region = (string) $school->region;
        $this->primary_color = $school->primary_color;
        $this->secondary_color = $school->secondary_color;
        $this->signatories = Setting::signatories();
    }

    public function saveSchool(): void
    {
        $this->authorize('manage-settings');
        $this->validate([
            'name' => 'required|string|max:255',
            'school_code' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'division' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('schoolvote.max_image_kb')],
        ]);

        $school = School::current();
        $data = $this->only(['name', 'school_code', 'address', 'division', 'region', 'primary_color', 'secondary_color']);
        if ($this->logo) {
            $data['logo_path'] = $this->logo->store('school', 'public');
        }
        School::whereKey($school->id)->first()->update($data);
        $this->logo = null;

        AuditLogger::log('settings.school_updated', $school);
        $this->dispatch('notify', message: 'School information saved.', type: 'success');
    }

    public function saveSignatories(): void
    {
        $this->authorize('manage-settings');
        $this->validate([
            'signatories' => 'array|max:4',
            'signatories.*.label' => 'required|string|max:50',
            'signatories.*.name' => 'nullable|string|max:120',
            'signatories.*.title' => 'required|string|max:120',
        ]);

        Setting::put('report.signatories', array_values($this->signatories));
        AuditLogger::log('settings.signatories_updated');
        $this->dispatch('notify', message: 'Report signatories saved.', type: 'success');
    }

    public function addSchoolYear(): void
    {
        $this->authorize('manage-settings');
        $this->validate(['newSchoolYear' => ['required', 'regex:/^\d{4}-\d{4}$/', 'unique:school_years,name']], [
            'newSchoolYear.regex' => 'Use the format 2026-2027.',
        ]);

        $sy = SchoolYear::create(['school_id' => School::current()->id, 'name' => $this->newSchoolYear, 'is_current' => false]);
        AuditLogger::log('settings.school_year_added', $sy, ['name' => $sy->name]);
        $this->newSchoolYear = '';
        $this->dispatch('notify', message: 'School year added.', type: 'success');
    }

    public function makeCurrent(int $id): void
    {
        $this->authorize('manage-settings');
        $sy = SchoolYear::findOrFail($id);
        $sy->makeCurrent();
        AuditLogger::log('settings.school_year_current', $sy, ['name' => $sy->name]);
        $this->dispatch('notify', message: "S.Y. {$sy->name} is now the current school year.", type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'school' => School::current(),
            'schoolYears' => SchoolYear::withCount('enrollments')->orderByDesc('name')->get(),
        ]);
    }
}
