<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerOutage extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'title',
        'status',
        'severity',
        'started_at',
        'estimated_end_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'estimated_end_at' => 'datetime',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(OutageReport::class);
    }

    public function neighborhoods(): BelongsToMany
    {
        return $this->belongsToMany(Neighborhood::class);
    }

    public function availableStatuses(): array
    {
        return match ($this->status) {
            self::STATUS_PLANNED => [
                self::STATUS_PLANNED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_IN_PROGRESS => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_RESOLVED,
            ],
            self::STATUS_RESOLVED, self::STATUS_CANCELLED => [$this->status],
            default => [$this->status],
        };
    }

    public function affectedAreasLabel(): string
    {
        return $this->neighborhoods->pluck('name')->implode(', ')
            ?: ($this->area ?? 'Area not specified');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeHistory(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CANCELLED]);
    }
}
