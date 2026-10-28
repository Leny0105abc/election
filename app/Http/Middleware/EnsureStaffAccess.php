<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff area gate: account must be active and have a role, and (when
 * configured) two-factor authentication must be enabled.
 */
class EnsureStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user || ! $user->is_active || ! $user->role()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account is not active. Contact the Super Administrator.']);
        }

        if (config('schoolvote.require_staff_two_factor')
            && ! $user->hasTwoFactorEnabled()
            && ! $request->routeIs('admin.account')) {
            return redirect()->route('admin.account')
                ->with('warning', 'Two-factor authentication is required. Please enable it to continue.');
        }

        return $next($request);
    }
}
