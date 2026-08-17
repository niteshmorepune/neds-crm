<?php

namespace App\Models;

use Database\Factories\WeeklyDigestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<WeeklyDigestFactory>
 */
class WeeklyDigest extends Model
{
    /** @use HasFactory<WeeklyDigestFactory> */
    use HasFactory;

    protected $fillable = [
        'digest_date',
        'summary',
        'pipeline_open_deals_count',
        'pipeline_open_value',
        'deals_won_count',
        'deals_lost_count',
        'mrr_total',
        'recurring_contracts_expiring_count',
        'cash_expected_this_month',
        'cash_expected_three_months',
        'receivables_total_outstanding',
        'receivables_overdue_ninety_plus_days',
        'client_radar_flagged_count',
        'client_radar_low_satisfaction_count',
        'client_radar_overdue_invoice_count',
        'visibility_audit_eligible_count',
        'visibility_audit_invited_count',
        'visibility_audit_landing_viewed_count',
        'visibility_audit_checkout_viewed_count',
        'visibility_audit_paid_count',
    ];

    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'pipeline_open_deals_count' => 'integer',
            'pipeline_open_value' => 'integer',
            'deals_won_count' => 'integer',
            'deals_lost_count' => 'integer',
            'mrr_total' => 'integer',
            'recurring_contracts_expiring_count' => 'integer',
            'cash_expected_this_month' => 'integer',
            'cash_expected_three_months' => 'integer',
            'receivables_total_outstanding' => 'integer',
            'receivables_overdue_ninety_plus_days' => 'integer',
            'client_radar_flagged_count' => 'integer',
            'client_radar_low_satisfaction_count' => 'integer',
            'client_radar_overdue_invoice_count' => 'integer',
            'visibility_audit_eligible_count' => 'integer',
            'visibility_audit_invited_count' => 'integer',
            'visibility_audit_landing_viewed_count' => 'integer',
            'visibility_audit_checkout_viewed_count' => 'integer',
            'visibility_audit_paid_count' => 'integer',
        ];
    }
}
