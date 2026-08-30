<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingSettingsRequest;
use App\Http\Requests\InvoiceNumberSettingsRequest;
use App\Http\Requests\ServiceRequest;
use App\Models\BillingSetting;
use App\Models\InvoiceNumberSequence;
use App\Models\Project;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin/manager management of the service-line taxonomy (SEO, GMB, Website, …)
 * referenced by leads, deals, projects, quotations and tickets. Replaces the
 * old "Categories" stub. Services in use can't be deleted — only deactivated —
 * so historical records keep their service.
 */
class ServiceController extends Controller
{
    public function index(InvoiceNumberGenerator $numbers): View
    {
        $currentFy = $numbers->financialYear(Carbon::now());

        return view('services.index', [
            'services' => Service::orderBy('sort_order')->orderBy('name')->get(),
            'defaultSacCode' => BillingSetting::current()->default_sac_code,
            'currentFy' => $currentFy,
            'nextDomesticPreview' => $numbers->peek(Carbon::now(), false),
            'nextExportPreview' => $numbers->peek(Carbon::now(), true),
            'nextDomesticNumber' => $numbers->peekNumber(Carbon::now(), false),
            'nextExportNumber' => $numbers->peekNumber(Carbon::now(), true),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] ??= (int) Service::max('sort_order') + 1;

        Service::create($data);

        return back()->with('status', 'Service added.');
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        $service->update($data);

        return back()->with('status', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $inUse = $service->leads()->exists()
            || $service->deals()->exists()
            || Project::where('service_id', $service->id)->exists()
            || Ticket::where('service_id', $service->id)->exists();

        if ($inUse) {
            return back()->with('status', 'That service is in use — deactivate it instead of deleting.');
        }

        $service->delete();

        return back()->with('status', 'Service removed.');
    }

    public function updateBillingSettings(BillingSettingsRequest $request): RedirectResponse
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

        return back()->with('status', "Invoice numbering for FY {$data['financial_year']} updated.");
    }
}
