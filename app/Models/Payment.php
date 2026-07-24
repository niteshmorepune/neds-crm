<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_id', 'paid_on', 'mode', 'reference', 'amount', 'tds_amount', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'mode' => PaymentMode::class,
            'amount' => 'integer',
            'tds_amount' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
