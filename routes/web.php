<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontHeatAlertController;
use App\Http\Controllers\HeatAdvisorController;
use App\Http\Controllers\HeatAlertController;
use App\Http\Controllers\MapAlertController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\NeighborhoodController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResidenceApprovalController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidenceMemberController;
use App\Http\Controllers\ResidentReportController;
use App\Http\Controllers\WeatherSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
});

// Login, registration, logout, password reset, email verification and 2FA routes come from Fortify.

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('profile.edit');
    Route::put('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('admin.dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('admin.users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])
            ->name('admin.users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])
            ->name('admin.users.update');

        Route::get('/residence-approvals', [ResidenceApprovalController::class, 'index'])
            ->name('admin.approvals.index');
        Route::post('/residence-approvals/{residence}/approve', [ResidenceApprovalController::class, 'approve'])
            ->name('admin.approvals.approve');
        Route::post('/residence-approvals/{residence}/reject', [ResidenceApprovalController::class, 'reject'])
            ->name('admin.approvals.reject');

        Route::post('/alerts/draft', [HeatAdvisorController::class, 'draft'])
            ->middleware('throttle:20,1')
            ->name('admin.alerts.draft');
        Route::post('/weather/sync', WeatherSyncController::class)->name('admin.weather.sync');

        Route::resource('neighborhoods', NeighborhoodController::class)->names('admin.neighborhoods');
        Route::resource('alerts', HeatAlertController::class)
            ->parameters(['alerts' => 'heatAlert'])
            ->names('admin.alerts');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('residences', ResidenceController::class);
    Route::post('residences/{residence}/members', [ResidenceMemberController::class, 'store'])
        ->name('residences.members.store');
    Route::delete('residences/{residence}/members/{user}', [ResidenceMemberController::class, 'destroy'])
        ->name('residences.members.destroy');
    Route::resource('resident-reports', ResidentReportController::class);
});

Route::get('/advisor/neighborhoods/{neighborhood}/brief', [HeatAdvisorController::class, 'brief'])
    ->middleware('throttle:20,1')
    ->name('advisor.brief');
Route::post('/advisor/neighborhoods/{neighborhood}/ask', [HeatAdvisorController::class, 'ask'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('advisor.ask');

Route::get('/map/data', MapDataController::class)->name('map.data');
Route::post('/map/alerts', [MapAlertController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('map.alerts.store');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/emergency', [PageController::class, 'emergency'])->name('pages.emergency');

Route::get('/heat-alerts', [FrontHeatAlertController::class, 'index'])->name('front.alerts.index');
