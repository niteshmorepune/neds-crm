<?php

namespace App\Models;

use App\Enums\ClientAdvanceStatus;
use App\Enums\PaymentMode;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientAdvance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'amount', 'amount_applied', 'received_on', 'mode',
        'reference', 'note', 'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'mode' => PaymentMode::class,
            'status' => ClientAdvanceStatus::class,
            'amount' => 'integer',
            'amount_applied' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remaining(): int
    {
        if ($this->status === ClientAdvanceStatus::Cancelled) {
            return 0;
        }

        return max(0, (int) $this->amount - (int) $this->amount_applied);
    }

    /**
     * Recompute amount_applied from the real Payment rows created against
     * this advance, and derive the resulting status. Never resurrects a
     * Cancelled advance — same rule as Invoice::refreshPaymentStatus()'s
     * own Cancelled guard.
     */
    public function refreshAppliedStatus(): void
    {
        if ($this->status === ClientAdvanceStatus::Cancelled) {
            return;
        }

        $applied = (int) $this->payments()->sum('amount');
        $this->amount_applied = $applied;

        $this->status = match (true) {
            $applied <= 0 => ClientAdvanceStatus::Outstanding,
            $applied >= (int) $this->amount => ClientAdvanceStatus::FullyApplied,
            default => ClientAdvanceStatus::PartiallyApplied,
        };

        $this->save();
    }
}
