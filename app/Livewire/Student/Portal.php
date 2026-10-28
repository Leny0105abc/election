<?php

namespace App\Livewire\Student;

use App\Models\Election;
use App\Models\ElectionVoter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.student')]
#[Title('My Elections')]
class Portal extends Component
{
    public function render()
    {
        $student = Auth::guard('student')->user();

        $elections = Election::visibleToStudents()->with('schoolYear')->get();

        $voterRecords = ElectionVoter::where('student_id', $student->id)
            ->whereIn('election_id', $elections->pluck('id'))
            ->get()
            ->keyBy('election_id');

        return view('livewire.student.portal', [
            'elections' => $elections,
            'voterRecords' => $voterRecords,
        ]);
    }
}
