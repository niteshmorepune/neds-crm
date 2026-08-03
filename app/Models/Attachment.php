<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type', 'attachable_id', 'uploaded_by', 'contact_id',
        'disk', 'path', 'original_name', 'mime_type', 'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Display name for who uploaded the file — an internal user, a portal
     * contact, or "System".
     */
    public function uploaderName(): string
    {
        return $this->uploader?->name ?? $this->contact?->name ?? 'System';
    }

    /**
     * Delete the underlying file when the record is removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
