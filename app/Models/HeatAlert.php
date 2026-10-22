<?php

namespace App\Models;

use App\Enums\Severity;
use App\Notifications\HeatAlertIssued;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class HeatAlert extends Model
{
    use HasFactory;

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_COMMUNITY = 'community';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'neighborhood_id',
        'title',
        'residence_name',
        'description',
        'image_path',
        'severity_level',
        'source',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'temperature',
        'latitude',
        'longitude',
        'created_by',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity_level' => Severity::class,
            'temperature' => 'decimal:1',
            'latitude' => 'float',
            'longitude' => 'float',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'notified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Neighborhood, $this>
     */
    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Alerts that may be shown to the public (admin-approved, or created by the admin or the weather).
     *
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('review_status', self::REVIEW_APPROVED);
    }

    /**
     * Resident reports waiting for an admin decision.
     *
     * @param  Builder<HeatAlert>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->where('review_status', self::REVIEW_PENDING);
    }

    /**
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->approved()
            ->where('start_date', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()));
    }

    /**
     * Active high-severity alerts from trusted sources (community reports never raise the banner).
     *
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeDanger(Builder $query): void
    {
        $query->active()
            ->where('severity_level', Severity::High->value)
            ->where('source', '!=', self::SOURCE_COMMUNITY);
    }

    /**
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->approved()->where('start_date', '>', now());
    }

    /**
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->approved()->where('end_date', '<', now());
    }

    /**
     * Approved alerts that are running or still to come (everything that is not expired).
     *
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeCurrentOrUpcoming(Builder $query): void
    {
        $query->approved()->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()));
    }

    /**
     * Alerts whose period intersects the given one (an open-ended period has no $end).
     *
     * @param  Builder<HeatAlert>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, ?CarbonInterface $end = null): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->when($end, fn (Builder $q) => $q->where('start_date', '<=', $end));
    }

    public function status(): string
    {
        return match (true) {
            $this->start_date->isFuture() => self::STATUS_UPCOMING,
            $this->end_date !== null && $this->end_date->isPast() => self::STATUS_EXPIRED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function isAutomatic(): bool
    {
        return $this->source === self::SOURCE_AUTO;
    }

    public function isCommunity(): bool
    {
        return $this->source === self::SOURCE_COMMUNITY;
    }

    public function isPendingReview(): bool
    {
        return $this->review_status === self::REVIEW_PENDING;
    }

    public function markReviewed(string $decision, User $reviewer): void
    {
        $this->forceFill([
            'review_status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();
    }

    public function hasPoint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isActive(): bool
    {
        return $this->status() === self::STATUS_ACTIVE;
    }

    /**
     * Notify the residents of the neighborhood once; returns how many were notified.
     */
    public function notifyResidents(): int
    {
        if ($this->notified_at !== null || $this->isCommunity() || ! $this->severity_level->notifiesResidents()) {
            return 0;
        }

        $residents = $this->neighborhood->residents()->get();

        Notification::send($residents, new HeatAlertIssued($this));
        $this->forceFill(['notified_at' => now()])->save();

        return $residents->count();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
