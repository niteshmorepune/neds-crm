<?php

namespace App\Models;

use App\Enums\LeadBudgetBand;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadUrgency;
use App\Models\Concerns\LogsActivity;
use App\Observers\LeadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(LeadObserver::class)]
class Lead extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'company',
        'phone',
        'email',
        'source',
        'service_id',
        'estimated_value',
        'owner_id',
        'status',
        'next_follow_up_at',
        'converted_customer_id',
        'converted_deal_id',
        'converted_at',
    ];

    /**
     * AI score columns are written by the ScoreLead job, not user forms, and are
     * noise in the activity log — exclude them so an automated re-score isn't
     * recorded as a user "update".
     *
     * @var list<string>
     */
    protected array $activityExcept = [
        'ai_score', 'ai_score_reason', 'ai_scored_at',
        'ai_budget_band', 'ai_urgency', 'ai_service_fit',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'estimated_value' => 'integer',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
            'ai_score' => 'integer',
            'ai_scored_at' => 'datetime',
            'ai_budget_band' => LeadBudgetBand::class,
            'ai_urgency' => LeadUrgency::class,
        ];
    }

    /** Hot leads get an immediate escalation notification instead of waiting for the digest. */
    public function isHot(): bool
    {
        return $this->ai_score !== null
            && $this->ai_score >= config('services.anthropic.hot_lead_threshold', 70);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'converted_deal_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function callLogs(): MorphMany
    {
        return $this->morphMany(CallLog::class, 'callable')->latest('called_at');
    }

    /**
     * All roles see all leads. Access to the leads page is controlled by
     * the menu.access:lead-generation middleware; visibility within is unrestricted.
     * Keep in sync with LeadPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query;
    }
}
