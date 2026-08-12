<?php

namespace App\Models;

use App\Enums\TeamResourceCategory;
use App\Models\Concerns\HasRoleVisibility;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Company-wide internal file library (plugin builds, certificates,
 * templates…) — Admin/Manager upload, everyone else gets a read-only,
 * role-filtered list (see HasRoleVisibility). Not per-client — that's what
 * ImportantLink's customer_id scope already covers.
 */
class TeamResource extends Model
{
    use HasFactory, HasRoleVisibility, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'category',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => TeamResourceCategory::class,
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function roleVisibilityModel(): string
    {
        return TeamResourceRole::class;
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

    protected static function booted(): void
    {
        static::deleting(function (TeamResource $resource) {
            Storage::disk($resource->disk)->delete($resource->path);
        });
    }
}
