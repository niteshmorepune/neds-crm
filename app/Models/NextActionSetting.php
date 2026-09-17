<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NextActionSetting extends Model
{
    protected $fillable = ['paused', 'updated_by'];

    protected function casts(): array
    {
        return [
            'paused' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The single settings row. Defaults to not-paused the first time it's read. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['paused' => false]);
    }
}
