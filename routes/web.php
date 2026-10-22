<?php

use App\Http\Controllers\Admin\CoolSpotController as AdminCoolSpotController;
use App\Http\Controllers\Admin\CoolSpotTypeController as AdminCoolSpotTypeController;
use App\Http\Controllers\AdminAdviceCategoryController;
use App\Http\Controllers\AdminAdviceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdviceAssistantController;
use App\Http\Controllers\AdviceCatalogController;
use App\Http\Controllers\AdviceSubmissionController;
use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\ConstellationController;
use App\Http\Controllers\CoolSpotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontHeatAlertController;
use App\Http\Controllers\HeatAdvisorController;
use App\Http\Controllers\HeatAlertController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidentLensController;
use App\Http\Controllers\MapAlertController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\MyOutageReportController;
use App\Http\Controllers\NeighborhoodController;
use App\Http\Controllers\OutageImpactSummaryController;
use App\Http\Controllers\OutageReportController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PowerOutageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizChallengeController;
use App\Http\Controllers\ReportFollowUpController;
use App\Http\Controllers\ReportPhotoController;
use App\Http\Controllers\ResidenceApprovalController;
use App\Http\Controllers\ResidenceController;
use App\Http\Controllers\ResidenceMemberController;
use App\Http\Controllers\ResidentReportController;
use App\Http\Controllers\ResponseCaseController;
use App\Http\Controllers\ResponseConfirmationController;
use App\Http\Controllers\ResponseTaskController;
use App\Http\Controllers\ResponseUpdateController;
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
    Route::get('/my-advice-submissions', [AdviceSubmissionController::class, 'index'])
        ->name('advice.submissions.index');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('profile.edit');
    Route::put('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authenticated front office: outages, advice, cool spots and heat alerts submitted by users.
Route::middleware('auth')->group(function () {
    Route::get('/advice-assistant', [AdviceAssistantController::class, 'index'])
        ->name('assistant.index');
    Route::post('/advice/conversations', [AdviceAssistantController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('assistant.conversations.store');
    Route::post('/advice/conversations/{conversation}/messages', [AdviceAssistantController::class, 'send'])
        ->middleware('throttle:10,1')
        ->whereNumber('conversation')
        ->name('assistant.messages.store');
});

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

Route::get('/quizzes', [QuizChallengeController::class, 'index'])->name('quizzes.index');
Route::get('/quizzes/history', [QuizChallengeController::class, 'history'])
    ->middleware(['auth', 'verified'])
    ->name('quizzes.history');
Route::get('/quizzes/{quiz}', [QuizChallengeController::class, 'show'])->name('quizzes.show');
Route::post('/quizzes/{quiz}/start', [QuizChallengeController::class, 'start'])
    ->middleware('throttle:20,1')
    ->name('quizzes.start');
Route::get('/quizzes/{quiz}/play/{run}', [QuizChallengeController::class, 'play'])->name('quizzes.play');
Route::post('/quizzes/{quiz}/play/{run}/answer', [QuizChallengeController::class, 'answer'])
    ->middleware('throttle:60,1')
    ->name('quizzes.answer');
Route::post('/quizzes/{quiz}/play/{run}/next', [QuizChallengeController::class, 'next'])
    ->middleware('throttle:60,1')
    ->name('quizzes.next');
Route::get('/quizzes/{quiz}/results/{run}', [QuizChallengeController::class, 'result'])->name('quizzes.results');
Route::post('/quizzes/{quiz}/results/{run}/review', [QuizChallengeController::class, 'review'])
    ->middleware('throttle:20,1')
    ->name('quizzes.review');

// Admin
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('admin.dashboard');
        Route::get('/quiz-statistics', [QuizChallengeController::class, 'statistics'])
            ->name('admin.quizzes.statistics');

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
        Route::patch('/advices/{advice}/approve', [AdminAdviceController::class, 'approve'])
            ->name('admin.advices.approve');
        Route::post('/advices/{advice}/reject', [AdminAdviceController::class, 'reject'])
            ->name('admin.advices.reject');
        Route::post('/advices/{advice}/request-changes', [AdminAdviceController::class, 'requestChanges'])
            ->name('admin.advices.request-changes');
        Route::post('/advices/{advice}/reanalyze', [AdminAdviceController::class, 'reanalyze'])
            ->middleware('throttle:10,1')
            ->name('admin.advices.reanalyze');

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

    // Incident Command Center: response cases, workflow, tasks, timeline and resident verification
    Route::get('command-center', CommandCenterController::class)->name('command-center.index');
    Route::get('residences/{residence}/response-cases/create', [ResponseCaseController::class, 'create'])->name('residences.response-cases.create');
    Route::post('residences/{residence}/response-cases', [ResponseCaseController::class, 'store'])->name('residences.response-cases.store');
    Route::get('response-cases/{responseCase}', [ResponseCaseController::class, 'show'])->name('response-cases.show');
    Route::patch('response-cases/{responseCase}', [ResponseCaseController::class, 'update'])->name('response-cases.update');
    Route::patch('response-cases/{responseCase}/stage', [ResponseCaseController::class, 'stage'])->name('response-cases.stage');
    Route::post('response-cases/{responseCase}/reports', [ResponseCaseController::class, 'linkReports'])->name('response-cases.reports.store');
    Route::delete('response-cases/{responseCase}/reports/{residentReport}', [ResponseCaseController::class, 'unlinkReport'])->name('response-cases.reports.destroy');
    Route::post('response-cases/{responseCase}/tasks', [ResponseTaskController::class, 'store'])->name('response-cases.tasks.store');
    Route::patch('response-tasks/{responseTask}', [ResponseTaskController::class, 'update'])->name('response-tasks.update');
    Route::delete('response-tasks/{responseTask}', [ResponseTaskController::class, 'destroy'])->name('response-tasks.destroy');
    Route::post('response-cases/{responseCase}/updates', [ResponseUpdateController::class, 'store'])->name('response-cases.updates.store');
    Route::patch('response-updates/{responseUpdate}/publish', [ResponseUpdateController::class, 'publish'])->name('response-updates.publish');
    Route::post('resident-reports/{residentReport}/confirmation', [ResponseConfirmationController::class, 'store'])->name('resident-reports.confirmation');

    // Command Center AI assistance (Constellation suggestions, Incident Lens on evidence)
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('residences/{residence}/response-cases/suggestions', [ResponseCaseController::class, 'suggestForResidence'])->name('residences.response-cases.suggestions');
        Route::get('response-cases/{responseCase}/suggestions', [ResponseCaseController::class, 'suggestForCase'])->name('response-cases.suggestions');
    });
    Route::post('response-cases/{responseCase}/evidence/{residentReport}', [ResponseCaseController::class, 'evidence'])
        ->middleware('throttle:10,1')
        ->name('response-cases.evidence');

    // AI Incident Constellation (managers and admins) and AI Incident Lens (report writing)
    Route::get('constellation', [ConstellationController::class, 'index'])->name('constellation.index');
    Route::get('constellation/graph', [ConstellationController::class, 'graph'])
        ->middleware('throttle:30,1')
        ->name('constellation.graph');
    Route::post('constellation/investigate', [ConstellationController::class, 'investigate'])
        ->middleware('throttle:10,1')
        ->name('constellation.investigate');
    Route::post('incident-lens/analyze', IncidentLensController::class)
        ->middleware('throttle:10,1')
        ->name('incident-lens.analyze');
    Route::get('report-photos/{residentReport}', ReportPhotoController::class)->name('report-photos.show');

    Route::get('managed-reports/{residentReport}', [ReportFollowUpController::class, 'show'])->name('managed-reports.show');
    Route::patch('managed-reports/{residentReport}', [ReportFollowUpController::class, 'update'])->name('managed-reports.update');
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
