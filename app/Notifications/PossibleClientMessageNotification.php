<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A WhatsApp message arrived from a phone number that matches no known
 * Customer or open Lead, but the message text (or a document's filename)
 * plausibly names an existing Client anyway — Customer::findMentionedInText(),
 * called from WhatsappWebhookController::handleUnmatchedNumber(). Real
 * incident, 2026-09-19: a contact at Exim Internationals (existing client,
 * active SEO + AMC) messaged from a personal number never on file and sent
 * a PDF named "Exim_Internationals_Website_Changes_Improvements.pdf" — with
 * no content check, that got filed as a brand-new Lead and got the generic
 * "what's your biggest goal?" qualifying question instead of routing to the
 * client. This is only a plausible match, not a certain one (unlike
 * Customer::findByPhone(), a real number match) — the message is logged to
 * the client's own timeline (see recordPossibleClientMessage()) rather than
 * silently treated as certain, and this notification is what lets staff
 * confirm it (e.g. add the number as a new Contact) or correct it.
 */
class PossibleClientMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Customer $customer,
        public string $phone,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'possible_client_message',
            'customer_id' => $this->customer->id,
            'phone' => $this->phone,
            'message' => "A WhatsApp message from an unrecognized number ({$this->phone}) mentions \"{$this->customer->company_name}\" by name. Held on {$this->customer->company_name}'s timeline for now, not filed as a new Lead. If this is really them, add the number as a Contact; otherwise it's a coincidental name match.",
            'url' => route('clients.show', $this->customer->id),
        ];
    }
}
