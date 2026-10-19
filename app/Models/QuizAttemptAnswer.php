<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = ['quiz_attempt_id', 'quiz_question_id', 'selected_option', 'is_correct', 'response_seconds'];

    protected function casts(): array
    {
        return [
            'selected_option' => 'integer',
            'is_correct' => 'boolean',
            'response_seconds' => 'integer',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
