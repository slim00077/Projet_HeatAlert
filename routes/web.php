<?php

use App\Http\Controllers\AdminAdviceCategoryController;
use App\Http\Controllers\AdminAdviceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdviceCatalogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
});

Route::get('/advice', [AdviceCatalogController::class, 'index'])
    ->name('advice.index');
Route::get('/advice/{advice}', [AdviceCatalogController::class, 'show'])
    ->name('advice.show');

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

        Route::resource('advices', AdminAdviceController::class)
            ->names('admin.advices');
        Route::resource('advice-categories', AdminAdviceCategoryController::class)
            ->names('admin.advice-categories');
        Route::resource('neighborhoods', \App\Http\Controllers\NeighborhoodController::class)->names('admin.neighborhoods');
        Route::resource('alerts', \App\Http\Controllers\HeatAlertController::class)->names('admin.alerts');
    });

Route::middleware('auth')->group(function () {
    Route::resource('residences', ResidenceController::class);
    Route::resource('resident-reports', ResidentReportController::class);
});

Route::get('/heat-alerts', [\App\Http\Controllers\FrontHeatAlertController::class, 'index'])->name('front.alerts.index');
