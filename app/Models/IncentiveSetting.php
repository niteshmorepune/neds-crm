<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveSetting extends Model
{
    protected $fillable = ['team_bonus_pool', 'updated_by'];

    protected function casts(): array
    {
        return [
            'team_bonus_pool' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The single settings row, defaulting the pool to ₹10,000 the first time it's read. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['team_bonus_pool' => 1_000_000]);
    }
}
