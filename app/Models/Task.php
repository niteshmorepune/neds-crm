<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title', 'description', 'project_id', 'assignee_id', 'created_by',
        'due_date', 'priority', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
        ];
    }

    /**
     * Stamp completed_at when a task transitions to Done (and clear it if it's
     * reopened), so the Employee Performance Report can measure completions and
     * on-time delivery regardless of which screen made the change.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if (! $task->isDirty('status')) {
                return;
            }

            if ($task->status === TaskStatus::Done) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assignee_id', $userId);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && ! $this->status->isComplete()
            && $this->due_date->isPast();
    }
}
