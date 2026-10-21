<?php

namespace App\Models;

use Database\Factories\ResponseTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A step of a response case, assigned to a staff member or to an external crew (assignee_label).
 */
class ResponseTask extends Model
{
    /** @use HasFactory<ResponseTaskFactory> */
    use HasFactory;

    public const STATUS_TODO = 'To Do';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_DONE = 'Done';

    public const STATUSES = [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_DONE];

    protected $fillable = ['title', 'description', 'assigned_to', 'assignee_label', 'status', 'progress', 'due_date'];

    protected $attributes = [
        'status' => self::STATUS_TODO,
        'progress' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Keeps status, progress and completed_at consistent whichever of them was changed.
     */
    protected static function booted(): void
    {
        static::saving(function (ResponseTask $task) {
            if ($task->isDirty('progress') && ! $task->isDirty('status')) {
                $task->status = match (true) {
                    $task->progress >= 100 => self::STATUS_DONE,
                    $task->progress > 0 => self::STATUS_IN_PROGRESS,
                    default => self::STATUS_TODO,
                };
            }

            if ($task->status === self::STATUS_DONE) {
                $task->progress = 100;
                $task->completed_at ??= now();
            } else {
                $task->progress = min($task->progress, 99);
                $task->completed_at = null;
            }

            if ($task->status === self::STATUS_TODO && $task->isDirty('status')) {
                $task->progress = 0;
            }
        });
    }

    public function responseCase(): BelongsTo
    {
        return $this->belongsTo(ResponseCase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function assigneeName(): ?string
    {
        return $this->assignee?->name ?? $this->assignee_label;
    }
}
