<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs students out after a period of inactivity (shared devices in
 * computer labs) and blocks accounts that were deactivated mid-session.
 */
class StudentSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        if ($student) {
            $timeout = (int) config('schoolvote.student_idle_timeout', 10) * 60;
            $last = (int) $request->session()->get('student_last_activity', time());

            if (! $student->is_active || (time() - $last) > $timeout) {
                Auth::guard('student')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('student.login')
                    ->with('status', 'You were signed out for your security. Please sign in again.');
            }

            $request->session()->put('student_last_activity', time());
        }

        return $next($request);
    }
}
