<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdviceModerationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'advice_id',
        'quality_report_id',
        'actor_id',
        'action',
        'note',
        'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function advice(): BelongsTo
    {
        return $this->belongsTo(Advice::class);
    }

    public function qualityReport(): BelongsTo
    {
        return $this->belongsTo(AdviceQualityReport::class, 'quality_report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
