<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An archived, superseded file from a ClientAsset's history — created once,
 * on replace (see ClientAsset::replaceFile()), and never edited afterward.
 */
class ClientAssetVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_asset_id', 'version', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function clientAsset(): BelongsTo
    {
        return $this->belongsTo(ClientAsset::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
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
        static::deleting(function (ClientAssetVersion $version) {
            Storage::disk($version->disk)->delete($version->path);
        });
    }
}
