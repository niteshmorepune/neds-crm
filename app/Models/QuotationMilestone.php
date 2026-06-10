<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id', 'title', 'percentage', 'amount', 'due_date', 'invoice_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'amount' => 'integer',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isBilled(): bool
    {
        return $this->invoice_id !== null;
    }
}
