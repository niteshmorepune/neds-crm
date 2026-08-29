<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A referral/content-collaborator agency. Also the authenticatable entity
 * for the partner portal (guard "partner") once portal access is granted
 * and a password is set — mirrors Contact's portal auth shape exactly.
 */
class Partner extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory, LogsActivity;

    protected $fillable = ['name', 'email', 'phone', 'notes', 'commission_rate', 'billing_customer_id'];

    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'portal_enabled' => 'boolean',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'password_set_at' => 'datetime',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referring_partner_id');
    }

    /**
     * When set, a reseller relationship — clients referred by this partner
     * are GST-invoiced to this Customer instead of billed directly.
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'billing_customer_id');
    }

    public function commissionStatements(): HasMany
    {
        return $this->hasMany(PartnerCommissionStatement::class);
    }

    /**
     * Referral settlements across all this partner's referred clients —
     * partner_id is denormalized directly onto referral_settlements (rather
     * than a hasManyThrough via Customer) since it's the natural scope for
     * both the internal Partner page and the Partner Portal.
     */
    public function referralSettlements(): HasMany
    {
        return $this->hasMany(ReferralSettlement::class);
    }

    /**
     * Quotations belonging to this partner: quotations for a directly
     * referred client, PLUS — when this is a reseller partner — quotations
     * billed to its own billingCustomer(). A reseller-referred client's
     * quotation is GST-billed to the partner's billing_customer at save
     * time (Customer::billingTarget()), so filtering on the referred
     * client's own referring_partner_id alone (as this query used to)
     * silently misses every reseller-billed quotation. Also includes
     * quotations for a referred client billed via a THIRD party
     * (Customer::billed_via_customer_id — a per-client redirect, distinct
     * from this partner's own billing_customer_id reseller field) — same
     * visibility gap, same fix, one level further removed.
     */
    public function quotations(): Builder
    {
        return Quotation::query()->where(function (Builder $query) {
            $query->whereHas('customer', fn (Builder $q) => $q->where('referring_partner_id', $this->id));

            if ($this->billing_customer_id !== null) {
                $query->orWhere('customer_id', $this->billing_customer_id);
            }

            $thirdPartyCustomerIds = $this->thirdPartyBillingCustomerIds();

            if ($thirdPartyCustomerIds->isNotEmpty()) {
                $query->orWhereIn('customer_id', $thirdPartyCustomerIds);
            }
        });
    }

    /**
     * Whether the given quotation belongs to this partner — either it's
     * for a directly referred client, or (reseller partners only) it's
     * billed to this partner's own billingCustomer(), or it's for a
     * referred client billed via a third party. Mirrors quotations() above
     * but for a single already-loaded model, so a controller doesn't need
     * to re-query to check ownership of a route-bound quotation.
     */
    public function ownsQuotation(Quotation $quotation): bool
    {
        return $quotation->customer?->referring_partner_id === $this->id
            || ($this->billing_customer_id !== null && $quotation->customer_id === $this->billing_customer_id)
            || $this->thirdPartyBillingCustomerIds()->contains($quotation->customer_id);
    }

    /**
     * The set of Customer ids this partner's own referred clients are
     * billed via (Customer::billed_via_customer_id), when any are set —
     * that's where a BilledViaThirdParty referred client's real quotations
     * actually live, since customer_id no longer points at the referred
     * client itself. See quotations()/ownsQuotation() above.
     */
    private function thirdPartyBillingCustomerIds(): Collection
    {
        return Customer::where('referring_partner_id', $this->id)
            ->whereNotNull('billed_via_customer_id')
            ->pluck('billed_via_customer_id');
    }

    public function hasCommission(): bool
    {
        return $this->commission_rate !== null && (float) $this->commission_rate > 0;
    }

    /**
     * Grant portal access and issue an invitation token (for the
     * set-password link). Returns the plain token.
     */
    public function inviteToPortal(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'portal_enabled' => true,
            'invitation_token' => hash('sha256', $token),
            'invited_at' => now(),
        ])->save();

        return $token;
    }

    public function revokePortalAccess(): void
    {
        $this->forceFill([
            'portal_enabled' => false,
            'invitation_token' => null,
            'password' => null,
            'password_set_at' => null,
        ])->save();
    }

    public function hasPortalAccess(): bool
    {
        return $this->portal_enabled && $this->password !== null;
    }
}
