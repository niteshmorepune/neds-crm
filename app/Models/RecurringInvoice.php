<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'service_id', 'frequency', 'day_of_month',
        'start_date', 'end_date', 'next_run_on', 'is_active',
        'last_reminder_sent_at', 'discount', 'is_gst_exempt', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_on' => 'date',
            'last_reminder_sent_at' => 'date',
            'is_active' => 'boolean',
            'discount' => 'integer',
            'is_gst_exempt' => 'boolean',
            'day_of_month' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Active templates whose next run is due on or before the given date. */
    public function scopeDue(Builder $query, $date): Builder
    {
        return $query->where('is_active', true)->whereDate('next_run_on', '<=', $date);
    }
}
