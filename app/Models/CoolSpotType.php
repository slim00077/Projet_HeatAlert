<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoolSpotType extends Model
{
    protected $fillable = ['name', 'icon', 'description'];

    public function coolSpots(): HasMany
    {
        return $this->hasMany(CoolSpot::class);
    }
}