<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fires the moment a lead's goal becomes "Not Sure – Need Expert Advice" —
 * from EITHER the telecaller UI (leads/show.blade.php's goal-capture panel/
 * Log a Call form) OR wadesk.in's after-hours WhatsApp assistant (see
 * LeadContextController::updateGoal()). See LeadObserver::updated(). A
 * telecaller who just set this themselves already knows, but a lead who
 * answers this way to the WhatsApp bot after hours has nobody watching live
 * — this closes that gap for both paths with one notification, rather than
 * building two divergent "needs Sales" mechanisms.
 *
 * $isReminder=true is the follow-up nag App\Console\Commands\
 * EscalateNotSureLeads sends when this initial ping went unanswered for
 * a while -- reusing this class (a distinct 'type'/message, not a new
 * notification class) rather than inventing a parallel one for what is,
 * to the recipient, the same underlying ask.
 */
class LeadWantsExpertAdviceNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public bool $isReminder = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->isReminder
            ? "Still no follow-up — {$this->lead->name} asked for expert advice and nobody's called yet"
            : "{$this->lead->name} wants expert advice — schedule a call";

        return [
            'type' => $this->isReminder ? 'lead_wants_expert_advice_reminder' : 'lead_wants_expert_advice',
            'lead_id' => $this->lead->id,
            'message' => $message,
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
