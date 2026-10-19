<?php

use App\Http\Controllers\Admin\CoolSpotController as AdminCoolSpotController;
use App\Http\Controllers\Admin\CoolSpotTypeController as AdminCoolSpotTypeController;
use App\Http\Controllers\AdminAdviceCategoryController;
use App\Http\Controllers\AdminAdviceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdviceCatalogController;
use App\Http\Controllers\CoolSpotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontHeatAlertController;
use App\Http\Controllers\HeatAdvisorController;
use App\Http\Controllers\HeatAlertController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapAlertController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\MyOutageReportController;
use App\Http\Controllers\NeighborhoodController;
use App\Http\Controllers\OutageImpactSummaryController;
use App\Http\Controllers\OutageReportController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PowerOutageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResidenceApprovalController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidenceMemberController;
use App\Http\Controllers\ResidentReportController;
use App\Http\Controllers\WeatherSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/outages', [PowerOutageController::class, 'publicIndex'])->name('front.power-outages.index');
Route::get('/outages/history', [PowerOutageController::class, 'history'])->name('front.power-outages.history');
Route::get('/outages/{powerOutage}', [PowerOutageController::class, 'publicShow'])
    ->whereNumber('powerOutage')
    ->name('front.power-outages.show');

// Login, registration, logout, password reset, email verification and 2FA routes come from Fortify.

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('profile.edit');
    Route::put('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authenticated front office: outages, advice, cool spots and heat alerts submitted by users.
Route::middleware(['auth', 'verified'])->group(function () {
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

    Route::get('/cool-spots/create', [CoolSpotController::class, 'create'])->name('cool-spots.create');
    Route::post('/cool-spots', [CoolSpotController::class, 'store'])->name('cool-spots.store');
});

Route::get('/advice', [AdviceCatalogController::class, 'index'])->name('advice.index');
Route::get('/advice/{advice}', [AdviceCatalogController::class, 'show'])->name('advice.show');

Route::get('/cool-spots', [CoolSpotController::class, 'index'])->name('cool-spots.index');
Route::post('/cool-spots/recommend', [CoolSpotController::class, 'recommend'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('cool-spots.recommend');
Route::get('/cool-spots/{coolSpot}', [CoolSpotController::class, 'show'])->name('cool-spots.show');

// Admin
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('admin.dashboard');

        Route::resource('users', AdminUserController::class)
            ->except('show')
            ->names('admin.users');

        Route::get('/residence-approvals', [ResidenceApprovalController::class, 'index'])
            ->name('admin.approvals.index');
        Route::post('/residence-approvals/{residence}/approve', [ResidenceApprovalController::class, 'approve'])
            ->name('admin.approvals.approve');
        Route::post('/residence-approvals/{residence}/reject', [ResidenceApprovalController::class, 'reject'])
            ->name('admin.approvals.reject');

        Route::view('/settings', 'back.settings.index')
            ->name('admin.settings');

        Route::resource('advices', AdminAdviceController::class)
            ->names('admin.advices');

        Route::resource('advice-categories', AdminAdviceCategoryController::class)
            ->names('admin.advice-categories');

        Route::post('/alerts/draft', [HeatAdvisorController::class, 'draft'])
            ->middleware('throttle:20,1')
            ->name('admin.alerts.draft');
        Route::post('/weather/sync', WeatherSyncController::class)->name('admin.weather.sync');

        Route::resource('neighborhoods', NeighborhoodController::class)
            ->names('admin.neighborhoods');

        Route::resource('alerts', HeatAlertController::class)
            ->parameters(['alerts' => 'heatAlert'])
            ->names('admin.alerts');

        Route::get('/power-outages', [PowerOutageController::class, 'index'])
            ->name('admin.power-outages.index');
        Route::get('/power-outages/create', [PowerOutageController::class, 'create'])
            ->name('admin.power-outages.create');
        Route::post('/power-outages', [PowerOutageController::class, 'store'])
            ->name('admin.power-outages.store');
        Route::post('/power-outages/{powerOutage}/impact-summary', OutageImpactSummaryController::class)
            ->middleware('throttle:10,1')
            ->name('admin.power-outages.impact-summary');
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
            Route::post('cool-spots/generate-description', [AdminCoolSpotController::class, 'generateDescription'])
                ->middleware('throttle:10,1')
                ->name('cool-spots.generate-description');

            Route::resource('cool-spot-types', AdminCoolSpotTypeController::class)
                ->except('show');

            Route::resource('cool-spots', AdminCoolSpotController::class)
                ->except('show');
        });
    });

// Resident and residence manager area
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

// Heat Alerts - Front Office
Route::get('/heat-alerts', [FrontHeatAlertController::class, 'index'])
    ->name('front.alerts.index');
