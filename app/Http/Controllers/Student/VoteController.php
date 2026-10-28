<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\VotingException;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\VoterEligibilityService;
use App\Services\VoteSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Final ballot submission. The ballot page (Livewire) only helps students
 * choose; the decisive step is this ordinary POST, re-validated server side.
 */
class VoteController extends Controller
{
    public function store(Request $request, Election $election, VoteSubmissionService $service): RedirectResponse
    {
        $student = $request->user('student');

        try {
            $service->submit($election, $student, $this->selectionsFrom($request));
        } catch (VotingException $e) {
            if ($e->reason === VotingException::ALREADY_VOTED) {
                return redirect()->route('student.ballot.done', $election);
            }

            return redirect()->route('student.ballot', $election)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            // Unexpected failure (e.g. database unavailable). The transaction
            // rolled back, so nothing was recorded. Never claim success here.
            report($e);

            return redirect()->route('student.ballot', $election)
                ->with('error', 'Your ballot was NOT recorded because of a system problem. Please try again in a moment.');
        }

        return redirect()->route('student.ballot.done', $election)->with('just_submitted', true);
    }

    /** Confirmation page. Reads the database, so it is only shown when the vote truly exists. */
    public function done(Request $request, Election $election, VoterEligibilityService $eligibility): View|RedirectResponse
    {
        $voter = $eligibility->voterRecord($election, $request->user('student'));

        if (! $voter?->has_voted) {
            return redirect()->route('student.portal');
        }

        return view('student.submitted', [
            'election' => $election,
            'voter' => $voter,
            'justSubmitted' => (bool) $request->session()->get('just_submitted'),
        ]);
    }

    /**
     * Convert form input into positionId => candidate ids | "abstain".
     *
     * @return array<int, list<int|string>|string>
     */
    private function selectionsFrom(Request $request): array
    {
        $candidates = $request->input('selections', []);
        $abstain = $request->input('abstain', []);

        if (! is_array($candidates) || ! is_array($abstain)) {
            throw new VotingException(VotingException::INVALID_BALLOT);
        }

        $result = [];

        foreach ($candidates as $positionId => $ids) {
            $result[(int) $positionId] = array_values((array) $ids);
        }

        foreach ($abstain as $positionId => $flag) {
            if ($flag) {
                // Choosing candidates AND abstaining for the same position is invalid.
                if (! empty($result[(int) $positionId])) {
                    throw new VotingException(VotingException::INVALID_BALLOT);
                }
                $result[(int) $positionId] = VoteSubmissionService::ABSTAIN;
            }
        }

        return $result;
    }
}
