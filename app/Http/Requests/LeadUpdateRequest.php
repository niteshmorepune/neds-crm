<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use Illuminate\Validation\Rule;

class LeadUpdateRequest extends LeadStoreRequest
{
    /**
     * Once a lead is Converted, this form must never be able to change it
     * away from that — parent::rules() requires `status` to be one of
     * new/contacted/qualified/lost (Converted is reached only via the
     * Convert action), which is correct for creating a lead but meant a
     * Converted lead's real status was never a legal value to resubmit here.
     * leads/_form.blade.php now renders status read-only for a Converted
     * lead and omits the input entirely, so `sometimes` lets every other
     * field (phone, follow-up date, etc.) keep saving normally without a
     * status value present at all.
     *
     * Real incident (2026-09-02): 3 already-converted leads had their
     * status silently reset to Qualified by a routine edit (e.g. fixing a
     * follow-up date) — nothing else about the edit was wrong, this rule
     * was just the same on Create and Edit.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->route('lead')?->status === LeadStatus::Converted) {
            $rules['status'] = ['sometimes', Rule::in([LeadStatus::Converted->value])];
        }

        return $rules;
    }
}
