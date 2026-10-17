<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Residence extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'name', 'address', 'neighborhood', 'description', 'media_path', 'media_type',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /**
     * The owner: the residence manager who created it (or the resident for older residences).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Residents added to this residence by its manager.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'residence_user')->withTimestamps();
    }

    public function residentReports(): HasMany
    {
        return $this->hasMany(ResidentReport::class);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Residences a user may see: admins everything, managers their own submissions (any status),
     * residents the approved residences they own or were added to.
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager()) {
            $query->where('user_id', $user->id);

            return;
        }

        $query->approved()->where(
            fn (Builder $q) => $q->where('user_id', $user->id)->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id))
        );
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasVideo(): bool
    {
        return $this->media_path !== null && $this->media_type === 'video';
    }

    /**
     * Uploaded picture URL, or a bundled illustration when none was uploaded.
     */
    public function imageUrl(): string
    {
        if ($this->media_path !== null && $this->media_type === 'image') {
            return Storage::disk('public')->url($this->media_path);
        }

        return asset('images/residence-'.(($this->id % 3) + 1).'.svg');
    }

    public function videoUrl(): ?string
    {
        return $this->hasVideo() ? Storage::disk('public')->url($this->media_path) : null;
    }
}
