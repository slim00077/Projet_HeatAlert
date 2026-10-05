<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Residence extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'address', 'neighborhood', 'description', 'media_path', 'media_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function residentReports(): HasMany
    {
        return $this->hasMany(ResidentReport::class);
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
