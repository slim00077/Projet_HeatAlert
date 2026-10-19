<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentReport extends Model
{
    use HasFactory;

    public const STATUSES = ['Pending', 'In Progress', 'Resolved'];

    protected $fillable = ['residence_id', 'user_id', 'title', 'description', 'status', 'reported_at'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}
