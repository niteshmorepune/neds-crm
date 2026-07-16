<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
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

class Ticket extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'customer_id', 'service_id', 'assignee_id', 'created_by',
        'subject', 'description', 'priority', 'status', 'sla_due_at', 'sla_breach_notified_at', 'resolved_at',
        'channel', 'whatsapp_conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'sla_breach_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }

    public function satisfactionRating(): HasOne
    {
        return $this->hasOne(TicketSatisfactionRating::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /**
     * SLA is breached when the due time has passed and the ticket is still open.
     */
    public function isSlaBreached(): bool
    {
        return $this->status->isOpen()
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::Waiting->value,
        ]);
    }

    /**
     * Sales see only their own clients' tickets; support/manager/admin see all.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            return $query->whereHas('customer', fn ($c) => $c->visibleTo($user));
        }

        return $query;
    }
}
