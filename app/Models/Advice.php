<?php

namespace App\Models;

use App\Enums\Severity;
use Database\Factories\AdviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Advice extends Model
{
    /** @use HasFactory<AdviceFactory> */
    use HasFactory;

    protected $fillable = ['advice_category_id', 'title', 'summary', 'content', 'image_path', 'risk_level', 'is_approved'];

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

    public function scopeApproved(Builder $query): void
    {
        $query->where('is_approved', true);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
