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
        'tags',
        'owner_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => CustomerStatus::class,
        ];
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

    /**
     * Best email for billing correspondence: the primary contact's, else the
     * customer's own. Null if neither is set.
     */
    public function billingEmail(): ?string
    {
        return $this->primaryContact?->email ?: $this->email;
    }

    /**
     * Limit a query to the customers a user is allowed to see. Sales see their
     * own + unassigned; everyone else (admin/manager/support/accounts) sees all.
     * Mirrors CustomerPolicy::view — keep them in sync.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('owner_id', $user->id)->orWhereNull('owner_id');
            });
        }

        return $query;
    }
}
