<?php

use App\Http\Controllers\Admin\CoolSpotController as AdminCoolSpotController;
use App\Http\Controllers\Admin\CoolSpotTypeController as AdminCoolSpotTypeController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoolSpotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
});

// Membre 3 : points de fraîcheur (front office)
Route::get('/cool-spots', [CoolSpotController::class, 'index'])
    ->name('cool-spots.index');
Route::get('/cool-spots/{coolSpot}', [CoolSpotController::class, 'show'])
    ->name('cool-spots.show');

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

<<<<<<< Updated upstream
Route::middleware(['auth', 'admin'])
=======
Route::get('/cool-spots', [CoolSpotController::class, 'index'])->name('cool-spots.index');
Route::post('/cool-spots/recommend', [CoolSpotController::class, 'recommend'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('cool-spots.recommend');
Route::get('/cool-spots/{coolSpot}', [CoolSpotController::class, 'show'])->name('cool-spots.show');

// Admin
Route::middleware(['auth', 'verified', 'admin'])
>>>>>>> Stashed changes
    ->prefix('admin')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('admin.dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('admin.users.index');

        // Membre 3 : points de fraîcheur (back office)
        Route::name('admin.')->group(function () {
<<<<<<< Updated upstream
            Route::resource('cool-spot-types', AdminCoolSpotTypeController::class)->except('show');
            Route::resource('cool-spots', AdminCoolSpotController::class)->except('show');
=======
            Route::post('cool-spots/generate-description', [AdminCoolSpotController::class, 'generateDescription'])
                ->middleware('throttle:10,1')
                ->name('cool-spots.generate-description');

            Route::resource('cool-spot-types', AdminCoolSpotTypeController::class)
                ->except('show');

            Route::resource('cool-spots', AdminCoolSpotController::class)
                ->except('show');
>>>>>>> Stashed changes
        });
    });

Route::middleware('auth')->group(function () {
    Route::resource('residences', ResidenceController::class);
    Route::resource('resident-reports', ResidentReportController::class);
});