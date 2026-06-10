<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A customer-side person. Also the authenticatable entity for the customer
 * portal (guard "portal") once portal access is granted and a password is set.
 */
class Contact extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'name',
        'designation',
        'phone',
        'email',
        'is_primary',
        'portal_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'portal_enabled' => 'boolean',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'password_set_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Make this contact the sole primary for its customer.
     */
    public function makePrimary(): void
    {
        static::where('customer_id', $this->customer_id)
            ->whereKeyNot($this->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        if (! $this->is_primary) {
            $this->forceFill(['is_primary' => true])->save();
        }
    }

    /**
     * Grant portal access and issue an invitation token (for the set-password
     * link). Returns the plain token.
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
