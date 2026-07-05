<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Festival;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Support\Ai;

/**
 * Higher-level AI helpers built on AnthropicClient: draft replies and timeline
 * summaries. Each method assembles context from the record's interaction
 * history, calls Claude, and returns the generated text — or null when AI is
 * disabled or the call fails (callers degrade gracefully). Drafts are never
 * sent automatically; the user always edits and confirms.
 */
class AiAssistant
{
    /** Cap history items fed to the model to keep token usage bounded. */
    private const MAX_ITEMS = 30;

    public function __construct(private readonly AnthropicClient $client) {}

    public function draftTicketReply(Ticket $ticket): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $lines = [
            'Subject: '.$ticket->subject,
            'Status: '.$ticket->status->label().' | Priority: '.$ticket->priority->label(),
            'Client: '.$ticket->customer->company_name,
            '',
            'Original request:',
            $ticket->description,
            '',
            'Conversation so far:',
        ];

        foreach ($ticket->replies->take(self::MAX_ITEMS) as $reply) {
            if ($reply->is_internal) {
                $who = 'Internal note ('.$reply->authorName().')';
            } elseif ($reply->isFromCustomer()) {
                $who = 'Client';
            } else {
                $who = 'Support ('.$reply->authorName().')';
            }
            $lines[] = "- {$who}: {$reply->body}";
        }

        $system = <<<'PROMPT'
        You draft support replies for a digital-solutions agency in India. Write a
        professional, friendly reply to the CLIENT that moves the ticket forward,
        based on the conversation. Use the client's likely name where natural. Do
        not invent facts, prices, or commitments not present in the thread. Output
        only the reply body — no subject line, no "Dear/Regards" placeholders in
        brackets, no commentary.
        PROMPT;

        return $this->trimmed($this->client->message(
            feature: 'draft_ticket_reply',
            prompt: implode("\n", $lines),
            system: $system,
        ));
    }

    public function draftLeadFollowUp(Lead $lead): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $lead->loadMissing(['service', 'notes', 'callLogs']);

        $lines = [
            'Lead: '.$lead->name.($lead->company ? ' ('.$lead->company.')' : ''),
            'Interested in: '.($lead->service?->name ?? 'unspecified'),
            'Source: '.$lead->source->label(),
            '',
            'Recent interactions:',
        ];

        foreach ($lead->notes->take(self::MAX_ITEMS) as $note) {
            $lines[] = '- Note: '.$note->body;
        }

        foreach ($lead->callLogs->take(self::MAX_ITEMS) as $call) {
            $lines[] = '- Call ('.$call->direction->label().', '.$call->outcome->label().'): '.($call->notes ?: 'no notes');
        }

        $system = <<<'PROMPT'
        You draft short, warm follow-up messages a salesperson can send to a lead
        (suitable for email or WhatsApp). Keep it under 90 words, reference what
        the lead is interested in, and end with a clear, low-pressure next step.
        Do not invent prices or promises. Output only the message body.
        PROMPT;

        return $this->trimmed($this->client->message(
            feature: 'draft_lead_followup',
            prompt: implode("\n", $lines),
            system: $system,
        ));
    }

    public function draftFestivalGreeting(Festival $festival, Project $project): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $project->loadMissing('customer', 'service');

        $lines = [
            'Festival: '.$festival->name.' ('.$festival->date->format('d M Y').')',
            'Client: '.$project->customer->company_name,
            'Service: '.($project->service?->name ?? 'unspecified'),
        ];

        $system = <<<'PROMPT'
        You draft short, warm festival greeting captions a digital-solutions
        agency in India posts on behalf of its clients (Instagram/Facebook/Google
        Business). Write a festive, professional caption of about 40-60 words that
        naturally mentions the client's business name, wishes them and their
        customers well for the festival, and uses 1-2 relevant emojis. Do not
        invent offers, prices, or promises. Do not use hashtags unless they occur
        naturally. Output only the caption text.
        PROMPT;

        return $this->trimmed($this->client->message(
            feature: 'draft_festival_greeting',
            prompt: implode("\n", $lines),
            system: $system,
        ));
    }

    public function summarizeTicket(Ticket $ticket): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $lines = [
            'Subject: '.$ticket->subject,
            'Status: '.$ticket->status->label(),
            '',
            'Request: '.$ticket->description,
            '',
            'Replies:',
        ];

        foreach ($ticket->replies->take(self::MAX_ITEMS) as $reply) {
            $who = $reply->is_internal ? 'Internal' : ($reply->isFromCustomer() ? 'Client' : 'Support');
            $lines[] = "- {$who}: {$reply->body}";
        }

        return $this->trimmed($this->client->message(
            feature: 'summarize_ticket',
            prompt: implode("\n", $lines),
            system: $this->summarySystem(),
        ));
    }

    public function summarizeCustomer(Customer $customer): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $customer->loadMissing(['notes', 'callLogs', 'tickets']);

        $lines = ['Client: '.$customer->company_name, '', 'Notes:'];

        foreach ($customer->notes->take(self::MAX_ITEMS) as $note) {
            $lines[] = '- '.$note->body;
        }

        $lines[] = '';
        $lines[] = 'Calls:';
        foreach ($customer->callLogs->take(self::MAX_ITEMS) as $call) {
            $lines[] = '- '.$call->direction->label().' / '.$call->outcome->label().': '.($call->notes ?: 'no notes');
        }

        $lines[] = '';
        $lines[] = 'Tickets:';
        foreach ($customer->tickets->take(self::MAX_ITEMS) as $ticket) {
            $lines[] = '- ['.$ticket->status->label().'] '.$ticket->subject;
        }

        return $this->trimmed($this->client->message(
            feature: 'summarize_customer',
            prompt: implode("\n", $lines),
            system: $this->summarySystem(),
        ));
    }

    private function summarySystem(): string
    {
        return <<<'PROMPT'
        You summarize a client's recent activity for an internal team member who
        needs to get up to speed quickly. Give a concise summary: 3-6 bullet points
        covering the current situation, open issues, and any obvious next step.
        Base it only on the provided history — do not speculate. Output only the
        summary.
        PROMPT;
    }

    private function trimmed(?AiResult $result): ?string
    {
        $text = trim((string) ($result?->text ?? ''));

        return $text === '' ? null : $text;
    }
}
