<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ResidentReport extends Model
{
    use HasFactory;

    public const STATUSES = ['Pending', 'In Progress', 'Resolved'];

    public const STATUS_RESOLVED = 'Resolved';

    /**
     * Optional incident categories (older reports have none and fall back to their title).
     */
    public const CATEGORIES = [
        'cooling' => 'Cooling / air conditioning',
        'power' => 'Electricity / power',
        'water' => 'Water supply',
        'elevator' => 'Elevator / access',
        'ventilation' => 'Ventilation / shade',
        'building' => 'Building / structure',
        'safety' => 'Health & safety',
        'other' => 'Other',
    ];

    /**
     * An unresolved report older than this many days is considered overdue.
     */
    public const OVERDUE_AFTER_DAYS = 14;

    protected $fillable = ['residence_id', 'user_id', 'title', 'category', 'description', 'status', 'resolution_notes', 'reported_at', 'photo_path', 'ai_assisted'];

    /**
     * The semantic vector is internal: it is never serialized to the browser.
     *
     * @var list<string>
     */
    protected $hidden = ['embedding', 'embedding_signature', 'photo_path'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'date',
            'resolved_at' => 'datetime',
            'ai_assisted' => 'boolean',
            'embedding' => 'array',
        ];
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    /**
     * Text used for semantic comparison: what the problem is, not who reported it.
     */
    public function semanticText(): string
    {
        return trim($this->categoryLabel().'. '.$this->title.'. '.Str::limit($this->description, 1200, ''));
    }

    /**
     * Keeps resolved_at in sync with the existing status workflow, whoever changes the status.
     */
    protected static function booted(): void
    {
        static::saving(function (ResidentReport $report) {
            if ($report->status === self::STATUS_RESOLVED) {
                $report->resolved_at ??= now();
            } else {
                $report->resolved_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    /**
     * Incident Command Center cases handling this report (the report itself is never changed by them).
     */
    public function responseCases(): BelongsToMany
    {
        return $this->belongsToMany(ResponseCase::class, 'response_case_report')
            ->withPivot(['linked_by', 'source'])
            ->withTimestamps();
    }

    /**
     * The response case currently handling this report: an open one first, otherwise the latest.
     */
    public function currentResponseCase(): ?ResponseCase
    {
        return $this->responseCases
            ->sortBy([fn (ResponseCase $a, ResponseCase $b) => $b->isOpen() <=> $a->isOpen(), fn (ResponseCase $a, ResponseCase $b) => $b->id <=> $a->id])
            ->first();
    }

    public function scopeUnresolved(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->unresolved()->whereDate('reported_at', '<', now()->subDays(self::OVERDUE_AFTER_DAYS)->toDateString());
    }

    /**
     * Reports whose residence the user may oversee: all for admins, their residences' reports for
     * managers, and only their own reports for residents.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager()) {
            $query->whereHas('residence', fn (Builder $residence) => $residence->where('user_id', $user->id));

            return;
        }

        $query->where('user_id', $user->id);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isOverdue(): bool
    {
        return ! $this->isResolved() && $this->daysOpen() > self::OVERDUE_AFTER_DAYS;
    }

    /**
     * Days between the report date and its resolution (or today when still open).
     */
    public function daysOpen(): int
    {
        $end = $this->resolved_at ?? now();

        return (int) max(0, $this->reported_at->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
    }

    /**
     * Page where this viewer may open the full report, or null when they may only see a privacy-safe summary.
     */
    public function urlFor(User $viewer): ?string
    {
        if ($viewer->isAdmin() || ($viewer->isResident() && $this->user_id === $viewer->id)) {
            return route('resident-reports.show', $this);
        }

        if ($viewer->can('followUp', $this)) {
            return route('managed-reports.show', $this);
        }

        return null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Uncategorized';
    }

    /**
     * Key used to group reports describing the same recurring problem: the category when set,
     * otherwise the normalized title.
     */
    public function recurrenceKey(): string
    {
        return $this->category !== null && $this->category !== 'other'
            ? 'category:'.$this->category
            : 'title:'.Str::of($this->title)->lower()->squish()->value();
    }
}
