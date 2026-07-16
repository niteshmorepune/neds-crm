<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes at-risk / upsell-opportunity signals for active clients, purely
 * from data already in the CRM (no AI call here — that's on-demand per
 * client via AiAssistant::suggestClientAction, kept off this hot path to
 * avoid an AI call per client on every dashboard/page load).
 */
class ClientRadarService
{
    private const NO_CONTACT_DAYS = 14;

    private const ACTIVITY_WINDOW_DAYS = 30;

    private const LOW_SATISFACTION_THRESHOLD = 2;

    private const LOW_SATISFACTION_WINDOW_DAYS = 60;

    /**
     * @return Collection<int, array{customer: Customer, flags: array<string, array{label: string, detail: string}>}>
     */
    public function flaggedClients(): Collection
    {
        $activeServiceCount = Service::active()->count();

        return Customer::query()
            ->where('status', CustomerStatus::Active)
            ->with(['owner', 'notes', 'callLogs', 'tickets.satisfactionRating', 'invoices', 'projects.service', 'recurringInvoices.service'])
            ->get()
            ->map(fn (Customer $customer) => [
                'customer' => $customer,
                'flags' => $this->flagsFor($customer, $activeServiceCount),
            ])
            ->filter(fn (array $row) => $row['flags'] !== [])
            ->values();
    }

    /**
     * @return array<string, array{label: string, detail: string}>
     */
    private function flagsFor(Customer $customer, int $activeServiceCount): array
    {
        $flags = [];

        $lastTouch = collect([
            $customer->notes->max('created_at'),
            $customer->callLogs->max('called_at'),
            $customer->tickets->max('created_at'),
        ])->filter()->max();

        if ($lastTouch === null || $lastTouch->lt(now()->subDays(self::NO_CONTACT_DAYS))) {
            $flags['no_contact'] = [
                'label' => 'No Contact',
                'detail' => $lastTouch === null
                    ? 'No note, call, or ticket on record'
                    : 'Last touch '.$lastTouch->diffInDays(now()).' days ago',
            ];
        } elseif ($this->isActivityDeclining($customer)) {
            $flags['declining_activity'] = [
                'label' => 'Declining Activity',
                'detail' => 'Fewer touches in the last '.self::ACTIVITY_WINDOW_DAYS.' days than the '.self::ACTIVITY_WINDOW_DAYS.' before that',
            ];
        }

        if ($customer->invoices->contains(fn ($invoice) => $invoice->status === InvoiceStatus::Overdue)) {
            $flags['overdue_invoice'] = [
                'label' => 'Overdue Invoice',
                'detail' => 'Has at least one overdue invoice',
            ];
        }

        $usedServiceIds = $customer->projects->pluck('service_id')
            ->merge($customer->recurringInvoices->pluck('service_id'))
            ->filter()
            ->unique();

        if ($usedServiceIds->count() === 1 && $activeServiceCount > 1) {
            $serviceName = $customer->projects->first(fn ($p) => $p->service_id !== null)?->service?->name
                ?? $customer->recurringInvoices->first(fn ($ri) => $ri->service_id !== null)?->service?->name
                ?? 'one service';

            $flags['upsell_opportunity'] = [
                'label' => 'Growth Opportunity',
                'detail' => "Only using {$serviceName} — {$activeServiceCount} services available",
            ];
        }

        $recentLowRatings = $customer->tickets
            ->pluck('satisfactionRating')
            ->filter()
            ->filter(fn ($rating) => $rating->created_at->gt(now()->subDays(self::LOW_SATISFACTION_WINDOW_DAYS)))
            ->filter(fn ($rating) => $rating->rating <= self::LOW_SATISFACTION_THRESHOLD);

        if ($recentLowRatings->isNotEmpty()) {
            $count = $recentLowRatings->count();
            $flags['low_satisfaction'] = [
                'label' => 'Low Satisfaction',
                'detail' => 'Rated '.$recentLowRatings->min('rating')."/5 on a recent ticket".($count > 1 ? " ({$count} low ratings)" : ''),
            ];
        }

        return $flags;
    }

    private function isActivityDeclining(Customer $customer): bool
    {
        $recent = $this->touchCount($customer, now()->subDays(self::ACTIVITY_WINDOW_DAYS), now());
        $previous = $this->touchCount($customer, now()->subDays(self::ACTIVITY_WINDOW_DAYS * 2), now()->subDays(self::ACTIVITY_WINDOW_DAYS));

        return $previous > 0 && $recent < ($previous / 2);
    }

    private function touchCount(Customer $customer, Carbon $from, Carbon $to): int
    {
        $notes = $customer->notes->whereBetween('created_at', [$from, $to])->count();
        $calls = $customer->callLogs->whereBetween('called_at', [$from, $to])->count();
        $tickets = $customer->tickets->whereBetween('created_at', [$from, $to])->count();

        return $notes + $calls + $tickets;
    }
}
