<?php

namespace App\Models;

use App\Enums\ResponseStage;
use Database\Factories\ResponseCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incident Command Center response case: one coordinated response to one or more related resident reports.
 * The linked reports are never modified; the case carries its own workflow stage, tasks and timeline.
 */
class ResponseCase extends Model
{
    /** @use HasFactory<ResponseCaseFactory> */
    use HasFactory;

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    protected $fillable = ['title', 'summary', 'stage', 'priority', 'target_date'];

    protected $attributes = [
        'stage' => 'reported',
        'priority' => 'medium',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ResponseStage::class,
            'target_date' => 'date',
            'stage_changed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ResponseCase $case) {
            if ($case->isDirty('stage') || ! $case->exists) {
                $case->stage_changed_at = now();
            }

            if ($case->stage === ResponseStage::Resolved) {
                $case->resolved_at ??= now();
            } else {
                $case->resolved_at = null;
            }
        });
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(ResidentReport::class, 'response_case_report')
            ->withPivot(['linked_by', 'source'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ResponseTask::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ResponseUpdate::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(ResponseConfirmation::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('stage', '!=', ResponseStage::Resolved->value);
    }

    /**
     * Cases a staff member may manage: all for admins, the cases of their own residences for managers, none otherwise.
     */
    public function scopeManageableBy(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager()) {
            $query->whereHas('residence', fn (Builder $residence) => $residence->where('user_id', $user->id));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Cases that include at least one report filed by this resident.
     */
    public function scopeFollowedBy(Builder $query, User $user): void
    {
        $query->whereHas('reports', fn (Builder $report) => $report->where('resident_reports.user_id', $user->id));
    }

    public function isOpen(): bool
    {
        return $this->stage->isOpen();
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->target_date !== null && $this->target_date->lt(today());
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function priorityTone(): string
    {
        return self::toneForPriority((string) $this->priority);
    }

    /**
     * Matches the soft-* colour classes of the design system.
     */
    public static function toneForPriority(string $priority): string
    {
        return match ($priority) {
            'critical' => 'coral',
            'high' => 'orange',
            'low' => 'green',
            default => 'blue',
        };
    }

    /**
     * Average progress of the case's tasks (0 to 100), or null when no task was planned.
     */
    public function taskProgress(): ?int
    {
        return $this->tasks->isEmpty() ? null : (int) round($this->tasks->avg('progress'));
    }

    /**
     * Days the case has been open (or took to resolve).
     */
    public function ageInDays(): int
    {
        return (int) $this->created_at->copy()->startOfDay()->diffInDays(($this->resolved_at ?? now())->copy()->startOfDay());
    }
}
