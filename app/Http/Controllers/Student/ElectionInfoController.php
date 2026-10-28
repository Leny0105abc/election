<?php

namespace App\Http\Controllers\Student;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ResultFinalizationService;
use Illuminate\View\View;

class ElectionInfoController extends Controller
{
    public function candidates(Election $election): View
    {
        abort_if($election->status === ElectionStatus::Draft, 404);

        $positions = $election->positions()
            ->where('is_active', true)
            ->with(['candidates' => fn ($q) => $q->where('status', CandidateStatus::Approved)
                ->with(['student', 'partyList'])->orderBy('ballot_name')])
            ->get()
            ->filter(fn ($p) => $p->candidates->isNotEmpty());

        return view('student.candidates', compact('election', 'positions'));
    }

    public function results(Election $election, ResultFinalizationService $results): View
    {
        abort_unless($election->resultsArePublished(), 404);

        return view('student.results', [
            'election' => $election,
            'positions' => $results->officialResults($election),
        ]);
    }
}
