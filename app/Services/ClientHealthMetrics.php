<?php

namespace App\Services;

use App\Models\Customer;

/**
 * Manager panel doc's "Client Health Score" (Tier 2 #04, "expands Client
 * Radar"). Formula confirmed with the owner via AskUserQuestion,
 * 2026-08-09: a 0-100 score starting at 100 with a fixed deduction per
 * active ClientRadarService flag — no_contact -30, declining_activity -20,
 * overdue_invoice -25, low_satisfaction -25 — floored at 0.
 * upsell_opportunity is deliberately never scored; it's a positive/growth
 * signal, not a risk. Since no_contact and declining_activity are already
 * mutually exclusive in ClientRadarService::flagsFor() (an elseif), the two
 * never stack for the same client.
 *
 * Adds no new detection logic of its own — every score is a pure weighting
 * on top of flags ClientRadarService already computes (flagsForCustomer()
 * for one client, or scoreFor() applied per-row to
 * ClientRadarService::flaggedClients() for Client Radar's own list), so the
 * two pages can never disagree about WHY a client is flagged, only how
 * severely.
 */
class ClientHealthMetrics
{
    /**
     * @var array<string, int>
     */
    private const DEDUCTIONS = [
        'no_contact' => 30,
        'declining_activity' => 20,
        'overdue_invoice' => 25,
        'low_satisfaction' => 25,
    ];

    public function __construct(private readonly ClientRadarService $radar) {}

    /**
     * A single customer's score — see ClientRadarService::flagsForCustomer()
     * for why this doesn't scan the whole active client base to get there.
     */
    public function scoreForCustomer(Customer $customer): int
    {
        return $this->scoreFor($this->radar->flagsForCustomer($customer));
    }

    /**
     * @param  array<string, array{label: string, detail: string, ticket_id?: int}>  $flags
     */
    public function scoreFor(array $flags): int
    {
        $score = 100;

        foreach (self::DEDUCTIONS as $flag => $points) {
            if (isset($flags[$flag])) {
                $score -= $points;
            }
        }

        return max(0, $score);
    }
}
