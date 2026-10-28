<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Student sign-in with Student ID (or LRN) + PIN.
 */
class LoginController extends Controller
{
    /**
     * Hash of a random value, used so that unknown IDs take as long to
     * reject as wrong PINs (prevents account enumeration by timing).
     */
    private const DUMMY_HASH = '$2y$10$Qd9skQQZOYm0a2evpjoxkeEUbXCKOO34hUUtLKC2p1zfagvLDOpCu';

    public function create(): View
    {
        return view('student.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'string', 'max:12'],
        ], [
            'identifier.required' => 'Enter your Student ID or LRN.',
            'pin.required' => 'Enter your PIN.',
        ]);

        $student = Student::findByIdentifier($credentials['identifier']);
        $pinMatches = Hash::check($credentials['pin'], $student?->pin_hash ?: self::DUMMY_HASH);

        if (! $student || ! $student->pin_hash || ! $pinMatches || ! $student->is_active) {
            AuditLogger::log('student.login_failed', $student, [], 'Failed student sign-in');

            // One generic message for every failure reason.
            return back()
                ->withErrors(['identifier' => 'The Student ID/LRN or PIN is incorrect, or the account is not active.'])
                ->onlyInput('identifier');
        }

        $request->session()->regenerate();
        Auth::guard('student')->login($student);
        $request->session()->put('student_last_activity', time());

        $student->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLogger::log('student.login', $student);

        return redirect()->route('student.portal');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($student = Auth::guard('student')->user()) {
            AuditLogger::log('student.logout', $student);
        }

        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login')->with('status', 'You have signed out. Thank you!');
    }
}
