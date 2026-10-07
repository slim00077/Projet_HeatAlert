<?php

namespace App\Models;

use Database\Factories\AdviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Advice extends Model
{
    /** @use HasFactory<AdviceFactory> */
    use HasFactory;

    protected $fillable = ['advice_category_id', 'title', 'summary', 'content', 'image_path'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdviceCategory::class, 'advice_category_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
