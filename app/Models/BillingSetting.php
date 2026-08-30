<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSetting extends Model
{
    protected $fillable = ['default_sac_code', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The single settings row, defaulting the SAC/HSN code to 998314 the first time it's read. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['default_sac_code' => '998314']);
    }
}
