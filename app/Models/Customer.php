<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\PartnerCollectionMode;
use App\Enums\ProjectStatus;
use App\Enums\ReferralShareType;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_name',
        'gstin',
        'gst_exempt',
        'email',
        'phone',
        'alternate_phone',
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
        'referring_partner_id',
        'partner_collection_mode',
        'referral_share_rate',
        'referral_share_type',
        'referral_share_fixed_amount',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => CustomerStatus::class,
            'gst_exempt' => 'boolean',
            'partner_collection_mode' => PartnerCollectionMode::class,
            'referral_share_type' => ReferralShareType::class,
            'referral_share_fixed_amount' => 'integer',
            'referral_share_rate' => 'decimal:2',
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

            // Hard-delete recurring invoice line items + templates (no SoftDeletes on RecurringInvoice)
            $recurringIds = $customer->recurringInvoices()->pluck('id');
            RecurringInvoiceItem::whereIn('recurring_invoice_id', $recurringIds)->delete();
            $customer->recurringInvoices()->delete();

            // Soft-delete invoices; items/payments kept for financial audit (Invoice has SoftDeletes)
            $customer->invoices()->delete();

            // Hard-delete contacts, notes on the customer, and call logs
            $customer->contacts()->delete();
            $customer->notes()->delete();
            $customer->callLogs()->delete();
            $customer->meetings()->delete();
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

    /**
     * Contacts who can actually receive a portal notification — enabled
     * AND with a password set (matches Contact::hasPortalAccess()).
     */
    public function portalContacts(): HasMany
    {
        return $this->contacts()->where('portal_enabled', true)->whereNotNull('password');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function referringPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'referring_partner_id');
    }

    /**
     * Who a GST invoice for this customer should actually name as the buyer
     * — itself, unless it was referred by a reseller partner (one with its
     * own billing_customer_id set), in which case that partner's own
     * customer record is billed instead (e.g. Brand-Whiz's referred clients
     * are billed to Brand Whiz).
     */
    public function billingTarget(): self
    {
        return $this->referringPartner?->billingCustomer ?? $this;
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function callLogs(): MorphMany
    {
        return $this->morphMany(CallLog::class, 'callable')->latest('called_at');
    }

    public function meetings(): MorphMany
    {
        return $this->morphMany(Meeting::class, 'meetable')->latest('occurred_at');
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

    public function clientAdvances(): HasMany
    {
        return $this->hasMany(ClientAdvance::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function ticketSatisfactionRatings(): HasManyThrough
    {
        return $this->hasManyThrough(TicketSatisfactionRating::class, Ticket::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /** One responsible employee per (customer, service) — see ServiceAssignment. */
    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /** Service-specific links (Website URL, GBP link, social handles…). */
    public function clientServiceLinks(): HasMany
    {
        return $this->hasMany(ClientServiceLink::class);
    }

    /** The client's categorized Assets & Documents library. */
    public function clientAssets(): HasMany
    {
        return $this->hasMany(ClientAsset::class);
    }

    /** Per-client-service requirement checklist — see ClientRequirement. */
    public function clientRequirements(): HasMany
    {
        return $this->hasMany(ClientRequirement::class);
    }

    public function referralSettlements(): HasMany
    {
        return $this->hasMany(ReferralSettlement::class);
    }

    /** Whether GenerateRecurringInvoices is allowed to bill this client at all. */
    public function isPartnerCollected(): bool
    {
        return $this->partner_collection_mode === PartnerCollectionMode::PartnerCollects;
    }

    /**
     * Whether this client has a usable referral share configured at all —
     * either a real fixed amount, or a real (>0) percentage. Used to decide
     * ReferralSettlement eligibility so a client with neither set (the
     * default for every existing client) is silently skipped rather than
     * producing a ₹0 settlement row every month.
     */
    public function hasReferralShareConfigured(): bool
    {
        if ($this->referral_share_type === ReferralShareType::FixedAmount) {
            return (int) $this->referral_share_fixed_amount > 0;
        }

        return (float) ($this->referral_share_rate ?? 0) > 0;
    }

    /**
     * The single source of truth for turning a billed amount into a referral
     * share — a FIXED amount is exactly that every month regardless of the
     * real billed total (e.g. Ampra Biobact: client billed ₹15,000+GST, but
     * the referral share is a flat ₹10,000 either way, not a percentage that
     * would silently drift if billing changes). Reused by both
     * ReferralSettlementService::recordManualBilling() (PartnerCollects,
     * staff-entered billedAmount) and FinalizeReferralSettlements
     * (NedsCollects, billedAmount summed from real invoices).
     */
    public function referralShareAmount(int $billedAmount): int
    {
        if ($this->referral_share_type === ReferralShareType::FixedAmount) {
            return (int) $this->referral_share_fixed_amount;
        }

        return (int) round($billedAmount * (float) ($this->referral_share_rate ?? 0) / 100);
    }

    /** Quick-access links for this client (website, GBP, Drive, socials, payment links…). */
    public function links(): HasMany
    {
        return $this->hasMany(ImportantLink::class);
    }

    /**
     * Recurring templates minus orphans (see RecurringInvoice::isOrphaned()) —
     * the single source of truth for what the Services tab lists/counts, so
     * the tab's rows and its header count can never drift apart. Requires
     * recurringInvoices (and, for isOrphaned()'s own queries, each template's
     * invoices) to already be loaded/available.
     *
     * @return Collection<int, RecurringInvoice>
     */
    public function nonOrphanedRecurringInvoices(): Collection
    {
        return $this->recurringInvoices->reject(fn (RecurringInvoice $r) => $r->isOrphaned());
    }

    /**
     * Unique, sorted list of Service names this client is currently active
     * on — an is_active recurring template, or a project that isn't yet
     * Completed. The single source of truth for "what is this client
     * actually signed up for right now" (used by the Client List's Services
     * column and the new-client-onboarded notification) — never manually
     * entered, always derived live from the same rows the Services tab
     * itself renders. Requires recurringInvoices/projects (and each row's
     * service) to already be loaded/available to avoid an N+1.
     *
     * @return Collection<int, string>
     */
    public function activeServiceNames(): Collection
    {
        // ->toBase() before ->map(): once mapped to plain service-name
        // strings these are no longer Eloquent models, but map() on an
        // Eloquent Collection still returns `new static` (EloquentCollection)
        // — its merge()/dictionary logic then assumes model items and calls
        // getKey() on a string. toBase() drops to a plain Support Collection
        // first, where merge() just concatenates.
        $recurring = $this->recurringInvoices->toBase()
            ->where('is_active', true)
            ->map(fn (RecurringInvoice $r) => $r->service?->name);

        $projects = $this->projects->toBase()
            ->where('status', '!=', ProjectStatus::Completed)
            ->map(fn (Project $p) => $p->service?->name);

        return $recurring->merge($projects)->filter()->unique()->sort()->values();
    }

    /**
     * This client's own monthly-equivalent recurring value — sums
     * RecurringInvoice::monthlyEquivalentValue() (the single source of truth
     * for this formula) across active templates. Requires
     * recurringInvoices.items to already be loaded (avoids an N+1 on the
     * client show page, which already eager-loads it).
     */
    public function monthlyRecurringValue(): int
    {
        return (int) $this->recurringInvoices
            ->where('is_active', true)
            ->sum(fn (RecurringInvoice $template) => $template->monthlyEquivalentValue());
    }

    /**
     * The soonest end_date among this client's active recurring templates —
     * the next renewal/contract-lapse point to watch, same signal
     * SendContractRenewalReminders already alerts on. Null if none are set
     * to end. Requires recurringInvoices to already be loaded.
     */
    public function nextRenewalDate(): ?Carbon
    {
        return $this->recurringInvoices
            ->where('is_active', true)
            ->pluck('end_date')
            ->filter()
            ->sort()
            ->first();
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
     * Best phone for WhatsApp correspondence: the primary contact's, else
     * the customer's own. Null if neither is set. Same fallback shape as
     * billingEmail().
     */
    public function billingPhone(): ?string
    {
        return $this->primaryContact?->phone ?: $this->phone;
    }

    /**
     * Admins/managers/support/accounts see all clients.
     * Sales reps (including anyone with Sales as an additional role) see
     * only clients they own or that are unassigned. Mirrors CustomerPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('owner_id', $user->id)->orWhereNull('owner_id');
            });
        }

        return $query;
    }
}
