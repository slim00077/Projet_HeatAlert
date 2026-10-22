<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoolSpot extends Model
{
    protected $fillable = [
        'cool_spot_type_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'opening_hours',
        'is_accessible',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_accessible' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CoolSpotType::class, 'cool_spot_type_id');
    }

    public function scopeWithDistanceFrom(Builder $query, float $latitude, float $longitude): Builder
    {
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $pdo = $query->getConnection()->getPdo();
            $pdo->sqliteCreateFunction('RADIANS', 'deg2rad', 1);
            $pdo->sqliteCreateFunction('SIN', 'sin', 1);
            $pdo->sqliteCreateFunction('COS', 'cos', 1);
            $pdo->sqliteCreateFunction('POWER', 'pow', 2);
            $pdo->sqliteCreateFunction('SQRT', 'sqrt', 1);
            $pdo->sqliteCreateFunction('ASIN', 'asin', 1);
        }

        return $query
            ->select('cool_spots.*')
            ->selectRaw(
                'CASE WHEN cool_spots.latitude IS NULL OR cool_spots.longitude IS NULL THEN NULL ELSE 6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(cool_spots.latitude - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(cool_spots.latitude)) * POWER(SIN(RADIANS(cool_spots.longitude - ?) / 2), 2))) END AS distance_km',
                [$latitude, $latitude, $longitude]
            );
    }

    public function isOpenNow(): ?bool
    {
        $hours = trim((string) $this->opening_hours);

        if ($hours === '') {
            return null;
        }

        if (preg_match('/^(?:24\s*\/\s*7|open\s+24\s+hours)$/i', $hours) === 1) {
            return true;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})\s*[-–]\s*(\d{1,2}):(\d{2})$/u', $hours, $matches) !== 1) {
            return null;
        }

        [$openingHour, $openingMinute, $closingHour, $closingMinute] = array_map('intval', [
            $matches[1], $matches[2], $matches[3], $matches[4],
        ]);

        if ($openingHour > 23 || $closingHour > 23 || $openingMinute > 59 || $closingMinute > 59) {
            return null;
        }

        $openingTime = $openingHour * 60 + $openingMinute;
        $closingTime = $closingHour * 60 + $closingMinute;

        if ($openingTime === $closingTime) {
            return null;
        }

        $now = CarbonImmutable::now('Africa/Tunis');
        $currentTime = $now->hour * 60 + $now->minute;

        return $closingTime < $openingTime
            ? $currentTime >= $openingTime || $currentTime < $closingTime
            : $currentTime >= $openingTime && $currentTime < $closingTime;
    }
}
