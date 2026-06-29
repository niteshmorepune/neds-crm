<?php

namespace App\Models;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class ContentPiece extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id', 'partner_id', 'workflow_type', 'platform', 'status',
        'title', 'copy_text', 'google_drive_link', 'publish_date',
        'published_at', 'notes', 'upload_token', 'upload_token_expires_at',
        'smdost_content_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'workflow_type' => ContentWorkflowType::class,
            'platform' => ContentPlatform::class,
            'status' => ContentStatus::class,
            'publish_date' => 'date',
            'published_at' => 'datetime',
            'upload_token_expires_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function scopeForProject($query, Project $project): void
    {
        $query->where('project_id', $project->id);
    }

    public function isUploadTokenValid(): bool
    {
        return $this->upload_token !== null
            && $this->upload_token_expires_at !== null
            && $this->upload_token_expires_at->isFuture();
    }

    /** Generates (or refreshes) a partner upload token valid for 7 days. */
    public function generateUploadToken(): string
    {
        $this->updateQuietly([
            'upload_token' => Str::uuid()->toString(),
            'upload_token_expires_at' => now()->addDays(7),
        ]);

        return route('partner-upload.show', $this->upload_token);
    }
}
