<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Task;
use App\Notifications\MonthlyWinsNoteDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Drafts an AI "here's what we delivered this month" note for one client and
 * lands it as a staff-only Note on their timeline (never sent automatically —
 * an account manager reviews, personalizes, and sends it themselves).
 * Referenced by customer id, not a serialized model, so a re-run always sees
 * fresh data. AI failure is swallowed — this must never break the monthly
 * command or the client's own workflow.
 *
 * For clients Drishti manages (drishti_client_id set), also pulls real
 * marketing-delivery numbers (posts published, audits completed, action
 * items done) from Drishti's GET /api/clients/{id}/monthly-metrics — the
 * same counts Drishti's own weekly client digest already computes, just
 * over a full month instead of a week. That external call is independently
 * try/catched and degrades to zero on any failure; it never blocks the
 * CRM-only signals (tasks/tickets/payments) from still producing a note.
 */
class DraftMonthlyWinsNote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTIVITY_EVENT = 'monthly_wins_note_drafted';

    /** @param  string  $monthKey  Y-m of the month being reported on (e.g. "2026-06"). */
    public function __construct(public int $customerId, public string $monthKey) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        // Idempotency: one wins note per client per month (defense in depth —
        // the dispatching command already checks this before dispatching).
        if ($this->alreadyDrafted()) {
            return;
        }

        $customer = Customer::with('owner')->find($this->customerId);

        if ($customer === null) {
            return;
        }

        $month = Carbon::createFromFormat('Y-m', $this->monthKey)->startOfMonth();
        $wins = $this->winsFor($customer, $month);
        $drishti = $this->drishtiWinsFor($customer, $month);

        // Nothing to report — skip the AI call and don't create a hollow note.
        if ($wins['tasks_completed'] === 0 && $wins['tickets_resolved'] === 0 && $wins['amount_paid_paise'] === 0
            && $drishti['posts_published'] === 0 && $drishti['audits_completed'] === 0 && $drishti['action_items_done'] === 0) {
            return;
        }

        $draft = $ai->draftMonthlyWinsNote($customer, [
            'tasks_completed' => $wins['tasks_completed'],
            'tickets_resolved' => $wins['tickets_resolved'],
            'amount_paid' => Money::format($wins['amount_paid_paise']),
            'posts_published' => $drishti['posts_published'],
            'audits_completed' => $drishti['audits_completed'],
            'action_items_done' => $drishti['action_items_done'],
        ]);

        if ($draft === null) {
            return;
        }

        $customer->notes()->create([
            'user_id' => null,
            'body' => "✨ AI-drafted monthly update — review and personalize before sending to the client:\n\n{$draft}",
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'event' => self::ACTIVITY_EVENT,
            'changes' => ['month' => $this->monthKey],
        ]);

        $customer->owner?->notify(new MonthlyWinsNoteDrafted($customer, $month->format('F Y')));
    }

    private function alreadyDrafted(): bool
    {
        return Activity::where('subject_type', Customer::class)
            ->where('subject_id', $this->customerId)
            ->where('event', self::ACTIVITY_EVENT)
            ->whereJsonContains('changes->month', $this->monthKey)
            ->exists();
    }

    /**
     * @return array{tasks_completed: int, tickets_resolved: int, amount_paid_paise: int}
     */
    private function winsFor(Customer $customer, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $tasksCompleted = Task::whereHas('project', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('status', TaskStatus::Done)
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $ticketsResolved = $customer->tickets()
            ->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->whereBetween('resolved_at', [$from, $to])
            ->count();

        $amountPaid = Payment::whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value))
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        return [
            'tasks_completed' => $tasksCompleted,
            'tickets_resolved' => $ticketsResolved,
            'amount_paid_paise' => (int) $amountPaid,
        ];
    }

    /**
     * Real marketing-delivery numbers from Drishti (posts published, audits
     * completed, action items done) for clients Drishti manages. Zeroed out
     * (never an exception) when the client has no drishti_client_id, Drishti
     * isn't configured, or the call fails — this must never block the note.
     *
     * @return array{posts_published: int, audits_completed: int, action_items_done: int}
     */
    private function drishtiWinsFor(Customer $customer, Carbon $month): array
    {
        $zero = ['posts_published' => 0, 'audits_completed' => 0, 'action_items_done' => 0];

        if ($customer->drishti_client_id === null) {
            return $zero;
        }

        $baseUrl = rtrim((string) config('services.drishti.base_url'), '/');
        $serviceKey = (string) config('services.drishti.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return $zero;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->get("{$baseUrl}/api/clients/{$customer->drishti_client_id}/monthly-metrics", [
                    'from' => $month->copy()->startOfMonth()->toIso8601String(),
                    'to' => $month->copy()->endOfMonth()->toIso8601String(),
                ]);

            if (! $response->successful()) {
                Log::warning('Drishti monthly metrics fetch failed', [
                    'customer_id' => $customer->id,
                    'status' => $response->status(),
                ]);

                return $zero;
            }

            $data = $response->json('data') ?? [];

            return [
                'posts_published' => (int) ($data['postsPublished'] ?? 0),
                'audits_completed' => (int) ($data['auditsCompleted'] ?? 0),
                'action_items_done' => (int) ($data['actionItemsDone'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Drishti monthly metrics exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return $zero;
        }
    }
}
