<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'power_outage_id',
        'user_id',
        'reporter_name',
        'email',
        'description',
        'status',
    ];

    public function powerOutage(): BelongsTo
    {
        return $this->belongsTo(PowerOutage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
