<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdviceMessage extends Model
{
    protected $fillable = ['role', 'content', 'advice_ids'];

    protected function casts(): array
    {
        return [
            'advice_ids' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AdviceConversation::class, 'advice_conversation_id');
    }
}
