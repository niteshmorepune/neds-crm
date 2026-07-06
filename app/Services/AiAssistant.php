<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Festival;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Ai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * A short "here's your day" narrative for the morning digest, built from
     * the same collections `SendMorningDigest` already assembles. Never
     * invents items — only narrates what's given.
     */
    public function summarizeDailyPriorities(
        User $user,
        Collection $overdueTasks,
        Collection $dueTodayTasks,
        Collection $callFollowUps,
        Collection $leadFollowUps,
        Collection $dealFollowUps,
        Collection $openTickets,
    ): ?string {
        if (! Ai::enabled()) {
            return null;
        }

        $lines = [
            'Overdue tasks: '.$overdueTasks->count(),
            'Tasks due today: '.$dueTodayTasks->count(),
            'Call follow-ups due: '.$callFollowUps->count(),
            'Lead follow-ups due: '.$leadFollowUps->count(),
            'Deal follow-ups due: '.$dealFollowUps->count(),
            'Open tickets assigned: '.$openTickets->count(),
        ];

        $system = <<<'PROMPT'
        You write a short, warm "here's your day" summary for a staff member at a
        digital-solutions agency in India, based only on the counts given. Address
        them directly ("you have..."), highlight what's most urgent first, and keep
        it to 2-3 sentences (about 50 words). Do not invent specific items, names,
        or numbers beyond what's provided. Output only the summary.
        PROMPT;

        return $this->trimmed($this->client->message(
            feature: 'daily_priorities_summary',
            prompt: "Staff member: {$user->name}\n".implode("\n", $lines),
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

    /**
     * A management-facing narrative summary of the Employee Performance
     * Report for one period. Admin/Manager only by construction — callers
     * only reach this from the already role-gated report page. Never shown
     * to the employee it's about.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  Same shape as ReportMetrics::employeePerformance().
     */
    public function summarizeTeamPerformance(Collection $rows, Carbon $from, Carbon $to): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '- %s (%s): %d tasks completed, on-time %s%%, %d calls, %d leads converted, attendance %s%%, %d daily reports',
                $row['user'],
                $row['role'],
                $row['tasks_completed'],
                $row['on_time_pct'] ?? 'n/a',
                $row['calls_made'],
                $row['leads_converted'],
                $row['attendance_pct'] ?? 'n/a',
                $row['daily_reports'],
            );
        }

        $system = <<<'PROMPT'
        You write a concise management-facing summary of a small digital-solutions
        agency's team performance for internal use by the owner/manager. Base it
        ONLY on the numbers given — never invent a cause, reason, or context not
        evidenced by the data. Give 4-6 bullet points covering: notable trends,
        standout performers (positive), and anyone whose numbers suggest they may
        need support or follow-up (frame this as an observation, not a
        judgement). Output only the bullet points.
        PROMPT;

        $prompt = "Period: {$from->format('d M Y')} - {$to->format('d M Y')}\n\n".implode("\n", $lines);

        return $this->trimmed($this->client->message(
            feature: 'team_performance_summary',
            prompt: $prompt,
            system: $system,
        ));
    }

    /**
     * A suggested next action for an account manager, based on the risk/
     * opportunity flags ClientRadarService computed for one client. Called
     * on-demand (button click), never in a batch job, to keep AI cost tied
     * to clients someone actually looks at rather than the whole client base.
     *
     * @param  array<string, array{label: string, detail: string}>  $flags
     */
    public function suggestClientAction(Customer $customer, array $flags): ?string
    {
        if (! Ai::enabled()) {
            return null;
        }

        $lines = ['Client: '.$customer->company_name, '', 'Signals:'];

        foreach ($flags as $flag) {
            $lines[] = "- {$flag['label']}: {$flag['detail']}";
        }

        $system = <<<'PROMPT'
        You advise account managers at a digital-solutions agency in India on how
        to respond to a client health signal. Based only on the signals given,
        suggest ONE concrete next action the account manager should take this
        week (e.g. a check-in call, a specific service to pitch, tactfully
        chasing an overdue payment). Keep it to 2-3 sentences (about 50 words).
        Do not invent facts, prices, or client details beyond what's given.
        Output only the suggestion.
        PROMPT;

        return $this->trimmed($this->client->message(
            feature: 'client_radar_suggestion',
            prompt: implode("\n", $lines),
            system: $system,
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
