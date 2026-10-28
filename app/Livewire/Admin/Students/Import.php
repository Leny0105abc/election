<?php

namespace App\Livewire\Admin\Students;

use App\Models\SchoolYear;
use App\Services\StudentImportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Excel/CSV import with a preview step. Parsed rows are kept server-side in
 * the cache (not in the browser), and are re-validated when committed.
 */
#[Layout('components.layouts.admin')]
#[Title('Import students')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    public ?int $schoolYearId = null;

    public ?string $token = null;

    /** @var array{total: int, new: int, update: int, error: int}|null */
    public ?array $summary = null;

    public ?array $result = null;

    public bool $onlyErrors = false;

    public function mount(): void
    {
        $this->authorize('manage-students');
        $this->schoolYearId = SchoolYear::current()?->id;
    }

    private function cacheKey(): string
    {
        return 'student-import:'.auth()->id().':'.$this->token;
    }

    public function preview(StudentImportService $service): void
    {
        $this->authorize('manage-students');
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'schoolYearId' => ['required', 'exists:school_years,id'],
        ], ['file.mimes' => 'Upload an Excel (.xlsx) or CSV file.']);

        try {
            $parsed = $service->preview($this->file);
        } catch (Throwable $e) {
            report($e);
            $this->addError('file', 'The file could not be read. Use the template and save as .xlsx or .csv.');

            return;
        }

        $this->token = Str::random(32);
        Cache::put($this->cacheKey(), $parsed['rows'], now()->addMinutes(30));
        $this->summary = $parsed['summary'];
        $this->result = null;
    }

    public function commit(StudentImportService $service): void
    {
        $this->authorize('manage-students');
        $rows = $this->token ? Cache::pull($this->cacheKey()) : null;

        if (! $rows) {
            $this->dispatch('notify', message: 'The preview expired. Please upload the file again.', type: 'error');
            $this->resetImport();

            return;
        }

        $this->result = $service->commit($rows, SchoolYear::findOrFail($this->schoolYearId));
        $this->summary = null;
        $this->token = null;
        $this->file = null;
        $this->dispatch('notify', message: "Import complete: {$this->result['created']} new, {$this->result['updated']} updated.", type: 'success');
    }

    public function resetImport(): void
    {
        if ($this->token) {
            Cache::forget($this->cacheKey());
        }
        $this->reset(['file', 'token', 'summary', 'result']);
    }

    public function render()
    {
        $rows = $this->token ? (Cache::get($this->cacheKey()) ?? []) : [];

        if ($this->onlyErrors) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['action'] === 'error'));
        }

        return view('livewire.admin.students.import', [
            'rows' => array_slice($rows, 0, 500),
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'headings' => StudentImportService::HEADINGS,
        ]);
    }
}
