<?php

use App\Http\Controllers\Admin\CoolSpotController as AdminCoolSpotController;
use App\Http\Controllers\Admin\CoolSpotTypeController as AdminCoolSpotTypeController;
use App\Http\Controllers\AdminAdviceCategoryController;
use App\Http\Controllers\AdminAdviceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdviceCatalogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoolSpotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontHeatAlertController;
use App\Http\Controllers\HeatAlertController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyOutageReportController;
use App\Http\Controllers\NeighborhoodController;
use App\Http\Controllers\OutageReportController;
use App\Http\Controllers\PowerOutageController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/outages', [PowerOutageController::class, 'publicIndex'])->name('front.power-outages.index');
Route::get('/outages/{powerOutage}', [PowerOutageController::class, 'publicShow'])->name('front.power-outages.show');

Route::middleware('auth')->group(function () {
    Route::post('/outages/{powerOutage}/reports', [OutageReportController::class, 'publicStore'])
        ->name('front.power-outages.reports.store');
    Route::get('/my-outage-reports', [MyOutageReportController::class, 'index'])
        ->name('my-outage-reports.index');
    Route::patch('/my-outage-reports/{outageReport}', [MyOutageReportController::class, 'update'])
        ->name('my-outage-reports.update');
    Route::delete('/my-outage-reports/{outageReport}', [MyOutageReportController::class, 'destroy'])
        ->name('my-outage-reports.destroy');

    Route::get('/advice/create', [AdviceCatalogController::class, 'create'])->name('advice.create');
    Route::post('/advice', [AdviceCatalogController::class, 'store'])->name('advice.store');

    Route::get('/heat-alerts/create', [FrontHeatAlertController::class, 'create'])->name('front.alerts.create');
    Route::post('/heat-alerts', [FrontHeatAlertController::class, 'store'])->name('front.alerts.store');

});

Route::get('/advice', [AdviceCatalogController::class, 'index'])
    ->name('advice.index');

Route::get('/advice/{advice}', [AdviceCatalogController::class, 'show'])
    ->name('advice.show');

// Cool Spots - Front Office
Route::middleware('auth')->group(function () {
    Route::get('/cool-spots/create', [CoolSpotController::class, 'create'])->name('cool-spots.create');
    Route::post('/cool-spots', [CoolSpotController::class, 'store'])->name('cool-spots.store');
});

Route::get('/cool-spots', [CoolSpotController::class, 'index'])
    ->name('cool-spots.index');

Route::get('/cool-spots/{coolSpot}', [CoolSpotController::class, 'show'])
    ->name('cool-spots.show');

// Authentication
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

// Admin
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('admin.dashboard');

        Route::resource('users', AdminUserController::class)
            ->except('show')
            ->names('admin.users');

        Route::view('/settings', 'back.settings.index')
            ->name('admin.settings');

        Route::resource('advices', AdminAdviceController::class)
            ->names('admin.advices');

        Route::resource('advice-categories', AdminAdviceCategoryController::class)
            ->names('admin.advice-categories');

        Route::resource('neighborhoods', NeighborhoodController::class)
            ->names('admin.neighborhoods');

        Route::resource('alerts', HeatAlertController::class)
            ->names('admin.alerts');

        Route::get('/power-outages', [PowerOutageController::class, 'index'])
            ->name('admin.power-outages.index');
        Route::get('/power-outages/create', [PowerOutageController::class, 'create'])
            ->name('admin.power-outages.create');
        Route::post('/power-outages', [PowerOutageController::class, 'store'])
            ->name('admin.power-outages.store');
        Route::get('/power-outages/{powerOutage}', [PowerOutageController::class, 'show'])
            ->name('admin.power-outages.show');
        Route::get('/power-outages/{powerOutage}/edit', [PowerOutageController::class, 'edit'])
            ->name('admin.power-outages.edit');
        Route::put('/power-outages/{powerOutage}', [PowerOutageController::class, 'update'])
            ->name('admin.power-outages.update');
        Route::delete('/power-outages/{powerOutage}', [PowerOutageController::class, 'destroy'])
            ->name('admin.power-outages.destroy');

        Route::patch('/outage-reports/{outageReport}/status', [OutageReportController::class, 'updateStatus'])
            ->name('admin.outage-reports.status');
        Route::delete('/outage-reports/{outageReport}', [OutageReportController::class, 'adminDestroy'])
            ->name('admin.outage-reports.destroy');

        // Cool Spots - Back Office
        Route::name('admin.')->group(function () {
            Route::resource('cool-spot-types', AdminCoolSpotTypeController::class)
                ->except('show');

            Route::resource('cool-spots', AdminCoolSpotController::class)
                ->except('show');
        });
    });

// Resident
Route::middleware('auth')->group(function () {
    Route::resource('residences', ResidenceController::class);
    Route::resource('resident-reports', ResidentReportController::class);
});

// Heat Alerts - Front Office
Route::get('/heat-alerts', [FrontHeatAlertController::class, 'index'])
    ->name('front.alerts.index');
