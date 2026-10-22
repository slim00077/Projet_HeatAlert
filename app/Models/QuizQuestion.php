<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $hidden = ['correct_option'];

    protected $fillable = [
        'quiz_id',
        'topic',
        'prompt',
        'options',
        'correct_option',
        'explanation',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_option' => 'integer',
            'position' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function attemptAnswers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }
}
