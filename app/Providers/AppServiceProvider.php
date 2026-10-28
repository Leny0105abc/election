<?php

namespace App\Providers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Never allow migrate:fresh / db:wipe style commands in production.
        DB::prohibitDestructiveCommands(app()->isProduction());

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => User::passwordRule());

        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        $this->defineGates();
        $this->defineRateLimits();
        $this->listenForAuthEvents();
    }

    /** One Gate per permission; the role → permission map lives in App\Enums\Role. */
    private function defineGates(): void
    {
        foreach (Role::allPermissions() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }

    private function defineRateLimits(): void
    {
        RateLimiter::for('student-login', function (Request $request) {
            $identifier = Str::lower(trim((string) $request->input('identifier')));

            return [
                Limit::perMinute(config('schoolvote.student_login_attempts_per_id'))->by('id:'.$identifier),
                Limit::perMinute(config('schoolvote.student_login_attempts_per_ip'))->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('ballot', function (Request $request) {
            return Limit::perMinute(10)->by('ballot:'.($request->user('student')?->id ?: $request->ip()));
        });
    }

    private function listenForAuthEvents(): void
    {
        Event::listen(function (Login $event) {
            if ($event->guard === 'web' && $event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                AuditLogger::log('staff.login', $event->user);
            }
        });

        Event::listen(function (Logout $event) {
            if ($event->guard === 'web' && $event->user instanceof User) {
                AuditLogger::log('staff.logout', $event->user);
            }
        });
    }
}
