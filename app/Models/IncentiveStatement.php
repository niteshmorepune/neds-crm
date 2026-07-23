<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_start',
        'sales_value',
        'individual_incentive',
        'team_bonus_eligible',
        'team_bonus_share',
        'total_incentive',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'sales_value' => 'integer',
            'individual_incentive' => 'integer',
            'team_bonus_eligible' => 'boolean',
            'team_bonus_share' => 'integer',
            'total_incentive' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
