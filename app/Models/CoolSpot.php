<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoolSpot extends Model
{
    protected $fillable = [
        'cool_spot_type_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'opening_hours',
        'is_accessible',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_accessible' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CoolSpotType::class, 'cool_spot_type_id');
    }
}
