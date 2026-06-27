<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_name',
        'gstin',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'state_code',
        'pincode',
        'country',
        'tags',
        'owner_id',
        'status',
        'drishti_client_id',
        'smdost_client_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => CustomerStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $customer) {
            // Notes attached to the customer's deals (polymorphic — no SoftDeletes on Note)
            Note::where('notable_type', Deal::class)
                ->whereIn('notable_id', $customer->deals()->pluck('id'))
                ->delete();
            // Soft-delete deals (Deal has SoftDeletes)
            $customer->deals()->delete();

            // Hard-delete quotation sub-rows, then the quotations themselves (no SoftDeletes)
            $quotationIds = $customer->quotations()->pluck('id');
            QuotationItem::whereIn('quotation_id', $quotationIds)->delete();
            QuotationMilestone::whereIn('quotation_id', $quotationIds)->delete();
            $customer->quotations()->delete();

            // Hard-delete tasks, then soft-delete projects (Project has SoftDeletes)
            Task::whereIn('project_id', $customer->projects()->pluck('id'))->delete();
            $customer->projects()->delete();

            // Hard-delete ticket replies, then soft-delete tickets (Ticket has SoftDeletes)
            TicketReply::whereIn('ticket_id', $customer->tickets()->pluck('id'))->delete();
            $customer->tickets()->delete();

            // Soft-delete invoices; items/payments kept for financial audit (Invoice has SoftDeletes)
            $customer->invoices()->delete();

            // Hard-delete contacts, notes on the customer, and call logs
            $customer->contacts()->delete();
            $customer->notes()->delete();
            $customer->callLogs()->delete();
        });
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function callLogs(): MorphMany
    {
        return $this->morphMany(CallLog::class, 'callable')->latest('called_at');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /**
     * True when the client is outside India — GST does not apply (export of services).
     */
    public function isOverseas(): bool
    {
        return ! empty($this->country) && strtolower(trim($this->country)) !== 'india';
    }

    /**
     * Best email for billing correspondence: the primary contact's, else the
     * customer's own. Null if neither is set.
     */
    public function billingEmail(): ?string
    {
        return $this->primaryContact?->email ?: $this->email;
    }

    /**
     * Admins/managers/support/accounts see all clients.
     * Sales reps see only clients they own or that are unassigned.
     * Mirrors CustomerPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Sales) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('owner_id', $user->id)->orWhereNull('owner_id');
            });
        }

        return $query;
    }
}
