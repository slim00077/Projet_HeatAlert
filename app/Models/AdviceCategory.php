<?php

namespace App\Models;

use Database\Factories\AdviceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdviceCategory extends Model
{
    /** @use HasFactory<AdviceCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function advices(): HasMany
    {
        return $this->hasMany(Advice::class, 'advice_category_id');
    }
}
