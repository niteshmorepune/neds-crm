<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Project;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
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
    public function index(): View
    {
        return view('services.index', [
            'services' => Service::orderBy('sort_order')->orderBy('name')->get(),
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
}
