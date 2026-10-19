<?php

use App\Http\Controllers\Api\V1\HeatAlertApiController;
use App\Http\Controllers\Api\V1\NeighborhoodApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/neighborhoods', [NeighborhoodApiController::class, 'index'])->name('api.v1.neighborhoods.index');
    Route::get('/alerts/active', [HeatAlertApiController::class, 'active'])->name('api.v1.alerts.active');
});
