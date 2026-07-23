<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageSetting extends Model
{
    protected $fillable = ['monthly_budget_paise', 'updated_by'];

    protected function casts(): array
    {
        return [
            'monthly_budget_paise' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The single settings row. Defaults to 0 (no ceiling configured) the first time it's read. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['monthly_budget_paise' => 0]);
    }
}
