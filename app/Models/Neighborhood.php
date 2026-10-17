<?php

namespace App\Models;

use App\Enums\Severity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'latitude',
        'longitude',
        'population',
        'vulnerable_population',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'population' => 'integer',
            'vulnerable_population' => 'integer',
            'current_temperature' => 'float',
            'forecast_max_temperature' => 'float',
            'weather_updated_at' => 'datetime',
        ];
    }

    public function heatAlerts(): HasMany
    {
        return $this->hasMany(HeatAlert::class);
    }

    public function activeAlerts(): HasMany
    {
        return $this->hasMany(HeatAlert::class)->active();
    }

    /**
     * Approved residences, linked to the neighborhood by its name.
     */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class, 'neighborhood', 'name')->approved();
    }

    /**
     * Residence managers assigned to this neighborhood by an admin.
     */
    public function managers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_MANAGER);
    }

    /**
     * People living in (or responsible for) an approved residence of this neighborhood.
     *
     * @return Builder<User>
     */
    public function residents(): Builder
    {
        $inThisNeighborhood = fn (Builder $q) => $q->approved()->where('neighborhood', $this->name);

        return User::query()->where(
            fn (Builder $q) => $q->whereHas('residences', $inThisNeighborhood)->orWhereHas('memberResidences', $inThisNeighborhood)
        );
    }

    /**
     * Highest severity among the active alerts (eager load `activeAlerts` to avoid N+1).
     */
    public function currentRisk(): ?Severity
    {
        return $this->activeAlerts
            ->map(fn (HeatAlert $alert) => $alert->severity_level)
            ->sortByDesc(fn (Severity $severity) => $severity->weight())
            ->first();
    }

    /**
     * The neighborhood whose center is closest to the point, within $maxKm (haversine distance).
     */
    public static function nearestTo(float $latitude, float $longitude, float $maxKm = 25): ?self
    {
        return static::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn (self $neighborhood) => [$neighborhood, $neighborhood->distanceKmTo($latitude, $longitude)])
            ->filter(fn (array $pair) => $pair[1] <= $maxKm)
            ->sortBy(fn (array $pair) => $pair[1])
            ->map(fn (array $pair) => $pair[0])
            ->first();
    }

    public function distanceKmTo(float $latitude, float $longitude): float
    {
        $dLat = deg2rad($latitude - $this->latitude);
        $dLon = deg2rad($longitude - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function vulnerableShare(): ?float
    {
        if (! $this->population || $this->vulnerable_population === null) {
            return null;
        }

        return round($this->vulnerable_population / $this->population * 100, 1);
    }
}
