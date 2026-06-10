<?php

namespace App\Models;

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'customer_id',
        'service_id',
        'value',
        'stage',
        'owner_id',
        'next_follow_up_at',
        'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'value' => 'integer',
            'next_follow_up_at' => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    /**
     * Move the deal to a new stage. Won/Lost are terminal — once set, the deal
     * cannot move again. Returns false if the move is not allowed.
     */
    public function moveToStage(DealStage $stage): bool
    {
        if ($this->stage->isTerminal() && $this->stage !== $stage) {
            return false;
        }

        $this->stage = $stage;

        return $this->save();
    }

    /**
     * Sales see their own + unassigned; managers/admins see all.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            return $query->where(fn (Builder $q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'));
        }

        return $query;
    }
}
