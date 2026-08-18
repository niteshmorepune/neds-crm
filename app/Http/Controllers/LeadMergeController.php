<?php

namespace App\Http\Controllers;

use App\Actions\MergeLeads;
use App\Http\Requests\MergeLeadsRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadMergeController extends Controller
{
    /**
     * Review screen: pick exactly 2 leads on the index (checkboxes), land
     * here to choose which record survives and, per field, which of the two
     * leads' values to keep.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $this->authorize('merge', Lead::class);

        $ids = collect($request->query('ids', []))->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->count() !== 2) {
            return redirect()->route('leads.index')->with('error', 'Select exactly 2 leads to merge.');
        }

        $leads = Lead::whereIn('id', $ids)->get()->keyBy('id');

        if ($leads->count() !== 2) {
            return redirect()->route('leads.index')->with('error', 'One of the selected leads could not be found.');
        }

        return view('leads.merge', [
            'leadA' => $leads->get($ids[0]),
            'leadB' => $leads->get($ids[1]),
            'fields' => MergeLeadsRequest::MERGEABLE_FIELDS,
        ]);
    }

    public function store(MergeLeadsRequest $request, MergeLeads $action): RedirectResponse
    {
        $this->authorize('merge', Lead::class);

        $validated = $request->validated();

        $primary = Lead::findOrFail($validated['primary_id']);
        $duplicate = Lead::findOrFail($validated['duplicate_id']);

        // Resolve each field from the REAL lead records, never from raw
        // request input — field_source holds a lead id (validated to be one
        // of these exact two), so a tampered value can only ever pick
        // between the two leads' actual data, never inject an arbitrary value.
        $fields = collect($validated['field_source'])
            ->mapWithKeys(fn ($chosenLeadId, string $field) => [
                $field => ((int) $chosenLeadId === $primary->id ? $primary : $duplicate)->{$field},
            ])
            ->all();

        // The phone picker only keeps one number as `phone` — when the two
        // leads genuinely have different numbers (e.g. a Meta lead-form
        // submission and its own auto-sent WhatsApp confirmation carrying
        // two real numbers for the same person, per lead 236/237), preserve
        // the other as alternate_phone instead of losing it outright. Only
        // fills it when the surviving record doesn't already have one, so an
        // existing alternate_phone on $primary is never silently clobbered.
        if (filled($primary->phone) && filled($duplicate->phone) && $primary->phone !== $duplicate->phone && blank($primary->alternate_phone)) {
            $fields['alternate_phone'] = $fields['phone'] === $primary->phone ? $duplicate->phone : $primary->phone;
        }

        $merged = $action->handle($primary, $duplicate, $fields);

        return redirect()->route('leads.show', $merged)
            ->with('status', "Merged \"{$duplicate->name}\" into this lead.");
    }
}
