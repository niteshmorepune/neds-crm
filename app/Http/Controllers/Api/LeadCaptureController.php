<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadCaptureRequest;
use App\Models\Lead;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class LeadCaptureController extends Controller
{
    /**
     * Create an unassigned website lead from the public company website form.
     */
    public function store(LeadCaptureRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Prefer an explicit service_id; fall back to matching service name from
        // a website dropdown (case-insensitive).
        $serviceId = $data['service_id'] ?? null;
        if (! $serviceId && ! empty($data['service'])) {
            $serviceId = Service::where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($data['service']))])
                ->value('id');
        }

        $lead = Lead::create([
            'name'            => $data['name'],
            'company'         => $data['company'] ?? null,
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'service_id'      => $serviceId,
            'estimated_value' => Money::toPaise($data['estimated_value'] ?? null),
            'source'          => LeadSource::Website->value,
            'status'          => LeadStatus::New->value,
            'owner_id'        => null,
        ]);

        if (! empty($data['message'])) {
            $lead->notes()->create([
                'user_id' => null,
                'body' => $data['message'],
            ]);
        }

        return response()->json([
            'message' => 'Lead received.',
            'id' => $lead->id,
        ], 201);
    }
}
