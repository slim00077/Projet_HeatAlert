<?php

namespace App\Models;

use Database\Factories\AdviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advice extends Model
{
    /** @use HasFactory<AdviceFactory> */
    use HasFactory;

    protected $fillable = ['advice_category_id', 'title', 'summary', 'content'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdviceCategory::class, 'advice_category_id');
    }
}
