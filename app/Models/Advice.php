<?php

namespace App\Models;

use App\Enums\Severity;
use Database\Factories\AdviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Advice extends Model
{
    /** @use HasFactory<AdviceFactory> */
    use HasFactory;

    protected $fillable = [
        'advice_category_id',
        'submitted_by',
        'title',
        'summary',
        'content',
        'source_url',
        'image_path',
        'risk_level',
        'is_approved',
        'moderation_status',
    ];

    protected function casts(): array
    {
        return [
            'risk_level' => Severity::class,
            'is_approved' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdviceCategory::class, 'advice_category_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(AdviceQualityReport::class);
    }

    public function moderationEvents(): HasMany
    {
        return $this->hasMany(AdviceModerationEvent::class);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('is_approved', true);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return match ($this->category?->name) {
                'Hydration' => 'https://images.unsplash.com/photo-1582759959879-0091a6c5caf2?auto=format&fit=crop&w=1200&q=80',
                'Energy' => 'https://images.unsplash.com/photo-1768577908037-bbc3da95c1cf?auto=format&fit=crop&w=1200&q=80',
                'Equipment' => 'https://images.pexels.com/photos/5125690/pexels-photo-5125690.jpeg?auto=compress&dpr=1&h=750&w=1260',
                'Cooling and sun protection' => 'https://images.pexels.com/photos/8760436/pexels-photo-8760436.jpeg?auto=compress&dpr=1&h=750&w=1260',
                'Health and wellbeing' => 'https://images.pexels.com/photos/29372727/pexels-photo-29372727/free-photo-of-elderly-caregiver-assisting-senior-woman-at-home.jpeg?auto=compress&dpr=1&h=750&w=1260',
                default => 'https://images.pexels.com/photos/31401648/pexels-photo-31401648/free-photo-of-woman-in-sun-hat-on-a-beach-in-summer.jpeg?auto=compress&dpr=1&h=750&w=1260',
            };
        }

        if (str_starts_with($this->image_path, 'https://') || str_starts_with($this->image_path, 'http://')) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
