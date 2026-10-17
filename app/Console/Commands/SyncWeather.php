<?php

namespace App\Console\Commands;

use App\Services\WeatherForecastService;
use Illuminate\Console\Command;

class SyncWeather extends Command
{
    protected $signature = 'weather:sync';

    protected $description = 'Fetch the weather forecast of every neighborhood and update the automatic heat alerts';

    public function handle(WeatherForecastService $weather): int
    {
        $synced = $weather->syncAll();

        $this->info("Weather refreshed for {$synced} neighborhood(s).");

        return self::SUCCESS;
    }
}
