<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StudentTemplateController;
use App\Http\Controllers\Student\ElectionInfoController;
use App\Http\Controllers\Student\LoginController;
use App\Http\Controllers\Student\VoteController;
use App\Livewire\Admin;
use App\Livewire\Student;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student voters
|--------------------------------------------------------------------------
*/

Route::middleware('guest:student')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('student.login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:student-login')
        ->name('student.login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('student.logout');

Route::middleware(['auth:student', 'student.session'])->prefix('vote')->name('student.')->group(function () {
    Route::livewire('/', Student\Portal::class)->name('portal');
    Route::livewire('/elections/{election}/ballot', Student\Ballot::class)->name('ballot');
    Route::post('/elections/{election}/ballot', [VoteController::class, 'store'])
        ->middleware('throttle:ballot')
        ->name('ballot.submit');
    Route::get('/elections/{election}/submitted', [VoteController::class, 'done'])->name('ballot.done');
    Route::get('/elections/{election}/candidates', [ElectionInfoController::class, 'candidates'])->name('candidates');
    Route::get('/elections/{election}/results', [ElectionInfoController::class, 'results'])->name('results');
});

/*
|--------------------------------------------------------------------------
| Staff (Super Administrator, Election Administrator, Election Committee)
| Staff login, 2FA and password reset routes are registered by Fortify
| under /staff (see config/fortify.php).
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', Admin\Dashboard::class)->name('dashboard');
    Route::livewire('/account', Admin\Account::class)->name('account');

    // Elections
    Route::livewire('/elections', Admin\Elections\Index::class)->name('elections.index');
    Route::livewire('/elections/create', Admin\Elections\Form::class)
        ->middleware('can:manage-elections')->name('elections.create');
    Route::livewire('/elections/{election}', Admin\Elections\Show::class)->name('elections.show');
    Route::livewire('/elections/{election}/edit', Admin\Elections\Form::class)
        ->middleware('can:manage-elections')->name('elections.edit');
    Route::livewire('/elections/{election}/positions', Admin\Elections\Positions::class)->name('elections.positions');
    Route::livewire('/elections/{election}/party-lists', Admin\Elections\PartyLists::class)->name('elections.party-lists');
    Route::livewire('/elections/{election}/candidates', Admin\Elections\Candidates::class)->name('elections.candidates');
    Route::livewire('/elections/{election}/voters', Admin\Elections\Voters::class)
        ->middleware('can:view-voter-reports')->name('elections.voters');
    Route::livewire('/elections/{election}/results', Admin\Elections\Results::class)
        ->middleware('can:view-canvass')->name('elections.results');
    Route::livewire('/elections/{election}/reports', Admin\Elections\Reports::class)
        ->middleware('can:view-reports')->name('elections.reports');
    Route::get('/elections/{election}/reports/{report}/{format}', [ReportController::class, 'show'])
        ->whereIn('format', ['pdf', 'xlsx', 'print'])
        ->name('reports.show');

    // Position catalogue
    Route::livewire('/positions', Admin\Positions::class)->middleware('can:manage-elections')->name('positions');

    // Students
    Route::middleware('can:manage-students')->group(function () {
        Route::livewire('/students', Admin\Students\Index::class)->name('students.index');
        Route::livewire('/students/import', Admin\Students\Import::class)->name('students.import');
        Route::get('/students/import-template', StudentTemplateController::class)->name('students.template');
        Route::livewire('/students/pins', Admin\Students\Pins::class)->name('students.pins');
    });

    Route::livewire('/audit-logs', Admin\AuditLogs::class)->middleware('can:view-audit')->name('audit-logs');
    Route::livewire('/users', Admin\Users::class)->middleware('can:manage-users')->name('users');
    Route::livewire('/settings', Admin\Settings::class)->middleware('can:manage-settings')->name('settings');
});
