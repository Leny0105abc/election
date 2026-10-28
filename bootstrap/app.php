<?php

use App\Exceptions\ElectionStateException;
use App\Http\Middleware\EnsureStaffAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StudentSessionTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'staff' => EnsureStaffAccess::class,
            'student.session' => StudentSessionTimeout::class,
        ]);

        // Unauthenticated visitors: staff pages -> staff login, everything else -> student login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*', 'staff/*')
            ? route('login')
            : route('student.login'));

        // Already signed in: send each kind of user to their own home.
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('staff/*')
            ? route('admin.dashboard')
            : route('student.portal'));

        // Hostinger and most shared hosts sit behind a proxy that sets X-Forwarded-*.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ElectionStateException::class]);
    })->create();
