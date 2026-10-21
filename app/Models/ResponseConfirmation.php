<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resident's verification of their own report within a response case.
 */
class ResponseConfirmation extends Model
{
    protected $fillable = ['resident_report_id', 'user_id', 'is_resolved', 'comment'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_resolved' => 'boolean'];
    }

    public function responseCase(): BelongsTo
    {
        return $this->belongsTo(ResponseCase::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResidentReport::class, 'resident_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
