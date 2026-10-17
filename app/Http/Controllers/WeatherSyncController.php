<?php

namespace App\Http\Controllers;

use App\Services\WeatherForecastService;
use Illuminate\Http\RedirectResponse;

class WeatherSyncController extends Controller
{
    public function __invoke(WeatherForecastService $weather): RedirectResponse
    {
        $synced = $weather->syncAll();

        return back()->with(
            $synced > 0 ? 'success' : 'error',
            $synced > 0 ? "Weather refreshed for {$synced} neighborhood(s)." : 'Could not reach the weather service.'
        );
    }
}
