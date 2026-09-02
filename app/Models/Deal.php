<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Jobs\ProvisionClientExternallyJob;
use App\Jobs\SendWhatsappHandoffMessageJob;
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
use Illuminate\Support\Carbon;

class Deal extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'customer_id',
        'service_id',
        'value',
        'stage',
        'lost_reason',
        'ai_suggested_lost_reason',
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
            'lost_reason' => DealLostReason::class,
            'ai_suggested_lost_reason' => DealLostReason::class,
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

                // Welcome-to-support WhatsApp handoff — pre-sale communication
                // happened on the marketing line, post-sale moves to support.
                // No-ops until a Meta-approved template is configured; see the
                // job's own docblock.
                SendWhatsappHandoffMessageJob::dispatch($deal->customer_id);

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
     * The most recent real touch of any kind — a note, or a logged
     * activity (an edit; LogsActivity's own "created" row counts too, which
     * makes this fall back to roughly created_at for an untouched deal).
     * Shared by DraftDealStallFollowUps and DraftDealStallFollowUp so both
     * agree on exactly the same definition of "already handled this stale
     * period" — the stall-draft job's own Activity marker is itself one of
     * these rows, so once written it correctly counts as the deal's latest
     * touch until a genuine new note/edit supersedes it.
     */
    public function lastTouchedAt(): Carbon
    {
        return collect([
            $this->notes()->max('created_at'),
            $this->activities()->max('created_at'),
            $this->created_at,
        ])->filter()->map(fn ($value) => Carbon::parse($value))->max();
    }

    /**
     * Move the deal to a new stage. Won/Lost are terminal — once set, the deal
     * cannot move again. Returns false if the move is not allowed, same
     * boolean-return contract as the existing terminal-stage guard below —
     * moving to Lost without a reason is treated as just another disallowed
     * move, not a separate exception path. Deliberately only enforced here,
     * not in a model-level saving() guard: several existing tests (and any
     * future backfill/import) create an already-Lost Deal directly via the
     * factory/mass-assignment, which must keep working unconditionally.
     */
    public function moveToStage(DealStage $stage, ?DealLostReason $lostReason = null): bool
    {
        if ($this->stage->isTerminal() && $this->stage !== $stage) {
            return false;
        }

        if ($stage === DealStage::Lost && $lostReason === null) {
            return false;
        }

        $this->stage = $stage;

        if ($stage === DealStage::Lost) {
            $this->lost_reason = $lostReason;
        }

        return $this->save();
    }

    /**
     * Compares the AI's suggested Lost reason (persisted at suggestion time by
     * DealsBoard::suggestLostReason() / DealLostReasonField::suggest(), see
     * ai_suggested_lost_reason) against the reason actually saved -- used by
     * the Loss Reason report as a calibration signal on Phase 1's suggestion
     * quality, not to influence anything at save time.
     *
     * @return 'no_suggestion'|'accepted'|'overridden'|null null when the deal
     *                                                      isn't Lost yet (the comparison is meaningless before that).
     */
    public function aiSuggestionOutcome(): ?string
    {
        if ($this->stage !== DealStage::Lost) {
            return null;
        }

        if ($this->ai_suggested_lost_reason === null) {
            return 'no_suggestion';
        }

        return $this->ai_suggested_lost_reason === $this->lost_reason ? 'accepted' : 'overridden';
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
