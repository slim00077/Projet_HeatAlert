<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerOutage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'status',
        'area',
        'severity',
        'started_at',
        'estimated_end_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'estimated_end_at' => 'datetime',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(OutageReport::class);
    }
}
