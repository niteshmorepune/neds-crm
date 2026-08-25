<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Small, rarely-changed field (pick one responsible employee per service) —
 * plain controller + inline Alpine form on the client page, same "too small
 * to justify a Livewire component" precedent as the Payment inline-edit
 * (date/mode/reference) fix.
 */
class ServiceAssignmentController extends Controller
{
    public function store(Request $request, Customer $client): RedirectResponse
    {
        $this->authorize('manageServices', $client);

        $data = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
        ]);

        ServiceAssignment::updateOrCreate(
            ['customer_id' => $client->id, 'service_id' => $data['service_id']],
            ['user_id' => $data['user_id']]
        );

        return back()->with('status', 'Service assignment updated.');
    }

    public function destroy(ServiceAssignment $serviceAssignment): RedirectResponse
    {
        $this->authorize('manageServices', $serviceAssignment->customer);

        $serviceAssignment->delete();

        return back()->with('status', 'Service assignment removed.');
    }
}
