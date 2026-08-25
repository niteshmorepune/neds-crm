<?php

namespace App\Models;

use App\Enums\ClientAssetCategory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A client's categorized Assets & Documents library — always holds the
 * CURRENT file; every prior file a "Replace" superseded lives in
 * ClientAssetVersion. Same file-field shape as Attachment/TeamResource, kept
 * as its own standalone model (not the generic polymorphic Attachment) so it
 * can carry a category + service scope + version history, mirroring
 * TeamResource's own precedent for a categorized file library.
 */
class ClientAsset extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'service_id', 'category', 'title',
        'disk', 'path', 'original_name', 'mime_type', 'size',
        'uploaded_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'category' => ClientAssetCategory::class,
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ClientAssetVersion::class)->latest('version');
    }

    /**
     * "Replace/Upload New Version": archives the CURRENT file into a new
     * ClientAssetVersion row (the superseded version), then overwrites this
     * row's own file fields with the new upload and bumps `version`. The
     * physical old file is never deleted here — ClientAssetVersion's own
     * delete hook is what eventually cleans it up, if that version row is
     * ever removed.
     */
    public function replaceFile(UploadedFile $file, int $uploadedBy): void
    {
        $this->versions()->create([
            'version' => $this->version,
            'disk' => $this->disk,
            'path' => $this->path,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by' => $this->uploaded_by,
        ]);

        $path = $file->store('client-assets', 'local');

        $this->update([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'version' => $this->version + 1,
        ]);
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
        static::deleting(function (ClientAsset $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
            // Delete versions individually (not a bulk query delete) so each
            // one's own deleting hook fires and cleans up its stored file —
            // same reasoning as ProjectDeliverables::removeDeliverable().
            $asset->versions->each->delete();
        });
    }
}
