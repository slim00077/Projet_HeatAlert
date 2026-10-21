<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdviceQualityReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'advice_id',
        'analyzed_by',
        'analysis_status',
        'analyzed_model',
        'score',
        'summary',
        'findings',
        'source_status',
        'advice_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'findings' => 'array',
            'advice_snapshot' => 'array',
        ];
    }

    public function advice(): BelongsTo
    {
        return $this->belongsTo(Advice::class);
    }

    public function analyzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyzed_by');
    }

    public function moderationEvents(): HasMany
    {
        return $this->hasMany(AdviceModerationEvent::class, 'quality_report_id');
    }
}
