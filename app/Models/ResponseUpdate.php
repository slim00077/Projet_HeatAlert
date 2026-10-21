<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a response case's activity timeline. Internal entries stay with the residence team;
 * published entries (is_public) are shown to the residents whose reports are in the case.
 */
class ResponseUpdate extends Model
{
    public const KIND_CREATED = 'created';

    public const KIND_STAGE = 'stage';

    public const KIND_NOTE = 'note';

    public const KIND_TASK = 'task';

    public const KIND_LINK = 'link';

    public const KIND_CONFIRMATION = 'confirmation';

    protected $fillable = ['user_id', 'kind', 'body', 'meta', 'is_public'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ResponseUpdate $update) {
            if ($update->is_public) {
                $update->published_at ??= now();
            } else {
                $update->published_at = null;
            }
        });
    }

    public function responseCase(): BelongsTo
    {
        return $this->belongsTo(ResponseCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_public', true);
    }

    public function icon(): string
    {
        return match ($this->kind) {
            self::KIND_CREATED => 'plus',
            self::KIND_STAGE => 'arrow',
            self::KIND_TASK => 'check',
            self::KIND_LINK => 'file',
            self::KIND_CONFIRMATION => 'users',
            default => 'bell',
        };
    }

    public function tone(): string
    {
        return match ($this->kind) {
            self::KIND_STAGE => $this->meta['tone'] ?? 'blue',
            self::KIND_TASK => 'green',
            self::KIND_CONFIRMATION => ($this->meta['resolved'] ?? false) ? 'green' : 'coral',
            self::KIND_CREATED => 'coral',
            default => 'purple',
        };
    }
}
