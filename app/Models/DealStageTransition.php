<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealStageTransition extends Model
{
    protected $fillable = [
        'deal_id',
        'from_stage',
        'to_stage',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => DealStage::class,
            'to_stage' => DealStage::class,
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
