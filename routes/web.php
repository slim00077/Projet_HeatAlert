<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontHeatAlertController;
use App\Http\Controllers\HeatAlertController;
use App\Http\Controllers\MapAlertController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\NeighborhoodController;
use App\Http\Controllers\ResidenceApprovalController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidenceMemberController;
use App\Http\Controllers\ResidentReportController;
use App\Http\Controllers\WeatherSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])
        ->name('register');

    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])
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

        Route::post('/weather/sync', WeatherSyncController::class)->name('admin.weather.sync');

        Route::resource('neighborhoods', NeighborhoodController::class)->names('admin.neighborhoods');
        Route::resource('alerts', HeatAlertController::class)
            ->parameters(['alerts' => 'heatAlert'])
            ->names('admin.alerts');
    });

Route::middleware('auth')->group(function () {
    Route::resource('residences', ResidenceController::class);
    Route::post('residences/{residence}/members', [ResidenceMemberController::class, 'store'])
        ->name('residences.members.store');
    Route::delete('residences/{residence}/members/{user}', [ResidenceMemberController::class, 'destroy'])
        ->name('residences.members.destroy');
    Route::resource('resident-reports', ResidentReportController::class);
});

Route::get('/map/data', MapDataController::class)->name('map.data');
Route::post('/map/alerts', [MapAlertController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('map.alerts.store');

Route::get('/heat-alerts', [FrontHeatAlertController::class, 'index'])->name('front.alerts.index');
