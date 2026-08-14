<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts a new-lead alert to one shared Telegram group via the Bot API
 * (plain HTTP POST to sendMessage — no polling/webhook needed to send).
 *
 * No-ops (logs, never throws) until both TELEGRAM_BOT_TOKEN and
 * TELEGRAM_CHAT_ID are set — ships inert, starts working the moment the
 * owner creates the bot via @BotFather, adds it to the group, and sets both
 * env vars. Same failure-tolerance discipline as SendWhatsappHandoffMessageJob
 * — a Telegram outage must never block lead creation.
 */
class SendTelegramLeadAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if (! $botToken || ! $chatId) {
            return;
        }

        $lead = Lead::with('owner')->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $detail = $lead->company ? "{$lead->name} ({$lead->company})" : $lead->name;
        $source = $lead->source?->label() ?? 'unknown';
        $owner = $lead->owner?->name ?? 'unassigned';
        $text = "New lead: {$detail}\nSource: {$source}\nAssigned to: {$owner}\n".route('leads.show', $lead->id);

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (! $response->successful()) {
                Log::warning('SendTelegramLeadAlertJob: Telegram API returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendTelegramLeadAlertJob: HTTP call to Telegram failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
