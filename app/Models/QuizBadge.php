<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuizBadge extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'icon'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiz_badge_user')
            ->withPivot('awarded_at');
    }
}
