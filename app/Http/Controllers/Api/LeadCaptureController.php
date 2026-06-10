<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadCaptureRequest;
use App\Models\Lead;
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

        $lead = Lead::create([
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'estimated_value' => Money::toPaise($data['estimated_value'] ?? null),
            'source' => LeadSource::Website->value,
            'status' => LeadStatus::New->value,
            'owner_id' => null,
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
