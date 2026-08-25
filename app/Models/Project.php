<?php

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Enums\ProjectStatus;
use App\Enums\RecurringFrequency;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Jobs\CreateOnboardingTasks;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'customer_id', 'deal_id', 'service_id', 'owner_id',
        'status', 'requirement_status', 'start_date', 'end_date', 'description',
        'google_drive_folder_link',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'requirement_status' => DeliverableStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            if ($project->status === ProjectStatus::Active) {
                CreateOnboardingTasks::dispatch($project->id);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectDeliverable::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    /**
     * Rough delivery progress from task completion — null when there are no
     * tasks yet to measure against. A general signal only; milestone-billed
     * projects track "ready to invoice" separately via QuotationMilestone
     * status, since tasks aren't mapped one-to-one to billing milestones.
     */
    public function completionPercentage(): ?int
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return null;
        }

        $done = $this->tasks()->where('status', TaskStatus::Done)->count();

        return (int) round($done / $total * 100);
    }

    /**
     * The primary staff contact for this project — same "lead assignee, else
     * owner" resolution already used for portal display and auto-routing
     * elsewhere in the app.
     */
    public function schedulingContact(): ?User
    {
        return $this->assignees->firstWhere('pivot.role', 'lead') ?? $this->owner;
    }

    /**
     * Who an auto-generated delivery/maintenance task should go to: a
     * Support-role project assignee first, then a non-Sales pivot-role=lead
     * assignee, then a non-Sales project owner. Never Sales at any step —
     * these are delivery/maintenance tasks, not sales work, and a Sales rep
     * is frequently a project's owner_id simply because they were the deal
     * owner who won it (CreateProjectFromDeal defaults owner_id to the deal
     * owner). Returns null when nobody appropriate is on the project —
     * callers should skip creating the task rather than falling back to
     * Sales. Single source of truth for this resolution: previously
     * duplicated separately in DispatchScheduledTasks, CreateOnboardingTasks
     * and OnboardingTaskSuggestions, which let a Sales-exclusion fix land in
     * one copy and not the other two.
     */
    public function maintenanceAssignee(): ?User
    {
        $support = $this->assignees->first(fn (User $u) => $u->role === UserRole::Support);
        if ($support) {
            return $support;
        }

        $lead = $this->assignees->firstWhere('pivot.role', 'lead');
        if ($lead && $lead->role !== UserRole::Sales) {
            return $lead;
        }

        return $this->owner && $this->owner->role !== UserRole::Sales ? $this->owner : null;
    }

    /**
     * The Google Calendar appointment-scheduling link to offer the client for
     * this project: the primary contact's personal link, falling back to the
     * company-wide default. Null when neither is set.
     */
    public function schedulingLink(): ?string
    {
        return $this->schedulingContact()?->google_meet_scheduling_link
            ?: (config('company.meet_scheduling_link') ?: null);
    }

    /**
     * The billing plan (Monthly/Quarterly/Yearly) this project's service is
     * subscribed under, for display on the client portal — Project itself
     * has no plan concept, so this is read from the client's recurring
     * invoice template for the same service (preferring an active one, since
     * a client can have an older ended template for a service they've since
     * moved to a different cadence on). Null for one-off/project-based work
     * with no recurring billing at all.
     */
    public function planFrequency(): ?RecurringFrequency
    {
        if ($this->service_id === null) {
            return null;
        }

        return RecurringInvoice::where('customer_id', $this->customer_id)
            ->where('service_id', $this->service_id)
            ->orderByDesc('is_active')
            ->latest()
            ->first()
            ?->frequency;
    }
}
