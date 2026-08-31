<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingSettingsRequest;
use App\Http\Requests\InvoiceNumberSettingsRequest;
use App\Models\BillingSetting;
use App\Models\InvoiceNumberSequence;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin/manager billing configuration: the default SAC/HSN code pre-filled
 * on new Quotation/Invoice/Recurring Invoice line items, and the invoice
 * numbering sequence (matching the team's real Hitech numbering). Split out
 * from ServiceController/Services page (2026-08-30) since these are billing
 * settings, not part of the service-line taxonomy.
 */
class BillingSettingController extends Controller
{
    public function index(InvoiceNumberGenerator $numbers): View
    {
        $currentFy = $numbers->financialYear(Carbon::now());

        return view('billing-settings.index', [
            'defaultSacCode' => BillingSetting::current()->default_sac_code,
            'currentFy' => $currentFy,
            'nextDomesticPreview' => $numbers->peek(Carbon::now(), false, false),
            'nextExportPreview' => $numbers->peek(Carbon::now(), true, false),
            'nextNonGstPreview' => $numbers->peek(Carbon::now(), false, true),
            'nextDomesticNumber' => $numbers->peekNumber(Carbon::now(), false, false),
            'nextExportNumber' => $numbers->peekNumber(Carbon::now(), true, false),
            'nextNonGstNumber' => $numbers->peekNumber(Carbon::now(), false, true),
        ]);
    }

    public function updateSacDefault(BillingSettingsRequest $request): RedirectResponse
    {
        $setting = BillingSetting::current();
        $setting->update([
            'default_sac_code' => $request->validated('default_sac_code'),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Billing default updated.');
    }

    public function updateInvoiceNumbering(InvoiceNumberSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        InvoiceNumberSequence::updateOrCreate(
            ['financial_year' => $data['financial_year'], 'sequence_type' => 'domestic'],
            ['last_number' => $data['next_domestic_number'] - 1]
        );
        InvoiceNumberSequence::updateOrCreate(
            ['financial_year' => $data['financial_year'], 'sequence_type' => 'export'],
            ['last_number' => $data['next_export_number'] - 1]
        );
        InvoiceNumberSequence::updateOrCreate(
            ['financial_year' => $data['financial_year'], 'sequence_type' => 'non_gst'],
            ['last_number' => $data['next_non_gst_number'] - 1]
        );

        return back()->with('status', "Invoice numbering for FY {$data['financial_year']} updated.");
    }
}
