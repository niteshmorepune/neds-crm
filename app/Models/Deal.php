<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Jobs\ProvisionClientExternallyJob;
use App\Models\Concerns\LogsActivity;
use App\Notifications\DealWonNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'won_at',
        'stage_changed_at',
        'lead_id',
        'partner_id',
    ];

    /**
     * Stashed by the saving() hook (before Eloquent syncs $original) so the
     * saved() hook can still see the pre-change stage once the deal has an
     * id. Not a DB column — deliberately outside $fillable/casts.
     */
    private bool $hasPendingStageTransition = false;

    private ?string $pendingStageTransitionFrom = null;

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'value' => 'integer',
            'next_follow_up_at' => 'datetime',
            'won_at' => 'datetime',
            'stage_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Deal $deal) {
            if ($deal->isDirty('stage')) {
                if ($deal->stage === DealStage::Won) {
                    $deal->won_at ??= now();
                } else {
                    $deal->won_at = null;
                }

                $deal->stage_changed_at = now();

                // Capture now — getOriginal('stage') would already reflect
                // the new value by the time the saved() hook below fires.
                $original = $deal->exists ? $deal->getOriginal('stage') : null;
                $deal->pendingStageTransitionFrom = $original instanceof DealStage ? $original->value : $original;
                $deal->hasPendingStageTransition = true;
            }
        });

        static::saved(function (Deal $deal) {
            if ($deal->hasPendingStageTransition) {
                DealStageTransition::create([
                    'deal_id' => $deal->id,
                    'from_stage' => $deal->pendingStageTransitionFrom,
                    'to_stage' => $deal->stage->value,
                ]);
                $deal->hasPendingStageTransition = false;
                $deal->pendingStageTransitionFrom = null;
            }
        });

        static::updated(function (Deal $deal) {
            if ($deal->wasChanged('stage') && $deal->stage === DealStage::Won) {
                Customer::where('id', $deal->customer_id)
                    ->where('status', CustomerStatus::Prospect->value)
                    ->update(['status' => CustomerStatus::Active->value]);

                // Provision the customer in Drishti and SMDost. The job is
                // idempotent (skips if drishti_client_id already set) so it is
                // safe to dispatch even if the deal somehow reaches Won twice.
                ProvisionClientExternallyJob::dispatch($deal->customer_id);

                // Notify the deal owner + all admin/manager users.
                $notification = new DealWonNotification($deal);
                $recipients = User::where('is_active', true)
                    ->withAnyRole(UserRole::Admin, UserRole::Manager)
                    ->get();
                if ($deal->owner_id) {
                    $owner = User::find($deal->owner_id);
                    if ($owner && ! $recipients->contains('id', $owner->id)) {
                        $recipients = $recipients->push($owner);
                    }
                }
                $recipients->each(fn (User $u) => $u->notify($notification));
            }
        });
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest();
    }

    public function stageTransitions(): HasMany
    {
        return $this->hasMany(DealStageTransition::class)->latest();
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
