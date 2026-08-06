<?php

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectDeliverable extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id', 'title', 'instructions', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
