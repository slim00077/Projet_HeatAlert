<?php

namespace App\Services;

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the Open-Meteo forecast (free, no API key) and keeps automatic heat alerts in sync.
 */
class WeatherForecastService
{
    public const FORECAST_DAYS = 3;

    /**
     * @return array{current: float, daily: array<string, float>}|null Daily maximums keyed by Y-m-d.
     */
    public function forecastFor(Neighborhood $neighborhood): ?array
    {
        if (! $neighborhood->hasCoordinates()) {
            return null;
        }

        try {
            $data = Http::timeout(10)
                ->withOptions(['verify' => config('services.open_meteo.ca_bundle') ?: true])
                ->retry(2, 300, throw: false)
                ->get(config('services.open_meteo.url'), [
                    'latitude' => $neighborhood->latitude,
                    'longitude' => $neighborhood->longitude,
                    'current' => 'temperature_2m',
                    'daily' => 'temperature_2m_max',
                    'forecast_days' => self::FORECAST_DAYS,
                    'timezone' => config('app.timezone'),
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning("Weather lookup failed for {$neighborhood->name}: {$exception->getMessage()}");

            return null;
        }

        $dates = $data['daily']['time'] ?? [];
        $maximums = $data['daily']['temperature_2m_max'] ?? [];
        $current = $data['current']['temperature_2m'] ?? null;

        if ($current === null || $dates === [] || count($dates) !== count($maximums)) {
            return null;
        }

        return ['current' => (float) $current, 'daily' => array_combine($dates, array_map('floatval', $maximums))];
    }

    /**
     * Refresh the stored weather of a neighborhood and create/update/close its automatic alert.
     * Returns the automatic alert that is running or upcoming afterwards, if any.
     */
    public function sync(Neighborhood $neighborhood): ?HeatAlert
    {
        $forecast = $this->forecastFor($neighborhood);

        return $forecast === null ? null : $this->apply($neighborhood, $forecast);
    }

    /**
     * @param  array{current: float, daily: array<string, float>}  $forecast
     */
    private function apply(Neighborhood $neighborhood, array $forecast): ?HeatAlert
    {
        $neighborhood->forceFill([
            'current_temperature' => $forecast['current'],
            'forecast_max_temperature' => max($forecast['daily']),
            'weather_updated_at' => now(),
        ])->save();

        $hotDays = array_filter($forecast['daily'], fn (float $max) => Severity::fromTemperature($max) !== Severity::Low);
        $existing = $neighborhood->heatAlerts()->where('source', HeatAlert::SOURCE_AUTO)->currentOrUpcoming()->latest('start_date')->first();

        if ($hotDays === []) {
            $this->closeAutomaticAlert($existing);

            return null;
        }

        $peak = max($hotDays);
        $severity = Severity::fromTemperature($peak);
        $firstDay = Carbon::parse(array_key_first($hotDays), config('app.timezone'))->startOfDay();
        $lastDay = Carbon::parse(array_key_last($hotDays), config('app.timezone'))->endOfDay();

        $alert = $this->upsertAutomaticAlert($neighborhood, $existing, $severity, $peak, $firstDay, $lastDay);

        if ($alert->isActive()) {
            $alert->setRelation('neighborhood', $neighborhood)->notifyResidents();
        }

        return $alert;
    }

    /**
     * @return int Number of neighborhoods whose weather was refreshed.
     */
    public function syncAll(): int
    {
        $synced = 0;

        Neighborhood::query()->whereNotNull('latitude')->whereNotNull('longitude')->each(function (Neighborhood $neighborhood) use (&$synced) {
            $forecast = $this->forecastFor($neighborhood);

            if ($forecast === null) {
                return;
            }

            $this->apply($neighborhood, $forecast);
            $synced++;
        });

        return $synced;
    }

    private function closeAutomaticAlert(?HeatAlert $alert): void
    {
        if ($alert === null) {
            return;
        }

        $alert->isActive() ? $alert->update(['end_date' => now()->subSecond()]) : $alert->delete();
    }

    private function upsertAutomaticAlert(Neighborhood $neighborhood, ?HeatAlert $existing, Severity $severity, float $peak, Carbon $firstDay, Carbon $lastDay): HeatAlert
    {
        $title = "Forecast heat alert - {$neighborhood->name}";
        $description = "Automatic alert from the weather forecast: up to {$peak}°C expected.";

        if ($existing === null) {
            return $neighborhood->heatAlerts()->create([
                'title' => $title,
                'description' => $description,
                'severity_level' => $severity,
                'temperature' => $peak,
                'start_date' => $firstDay->isPast() ? now() : $firstDay,
                'end_date' => $lastDay,
                'source' => HeatAlert::SOURCE_AUTO,
            ]);
        }

        $escalated = $severity->weight() > $existing->severity_level->weight();

        $existing->fill([
            'title' => $title,
            'description' => $description,
            'severity_level' => $severity,
            'temperature' => $peak,
            'end_date' => $lastDay,
        ]);

        if ($existing->start_date->isFuture()) {
            $existing->start_date = $firstDay->isPast() ? now() : $firstDay;
        }

        if ($escalated) {
            $existing->notified_at = null;
        }

        $existing->save();

        return $existing;
    }
}
