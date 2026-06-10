<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_invoice_id', 'description', 'sac_code', 'quantity', 'rate', 'gst_rate', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'integer',
            'gst_rate' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }
}
