<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A referral/content-collaborator agency. Also the authenticatable entity
 * for the partner portal (guard "partner") once portal access is granted
 * and a password is set — mirrors Contact's portal auth shape exactly.
 */
class Partner extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory, LogsActivity;

    protected $fillable = ['name', 'email', 'phone', 'notes', 'commission_rate'];

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

    public function commissionStatements(): HasMany
    {
        return $this->hasMany(PartnerCommissionStatement::class);
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
