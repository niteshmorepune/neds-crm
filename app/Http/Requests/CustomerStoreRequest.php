<?php

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Rules\Gstin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route/controller authorizes via CustomerPolicy.
        return true;
    }

    /**
     * Normalise the comma-separated tags field into an array before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->tags)) {
            $this->merge([
                'tags' => collect(explode(',', $this->tags))
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'size:15', new Gstin, $this->gstinUniqueRule()],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', Rule::in(array_keys(config('india.states')))],
            'pincode' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
        ];
    }

    protected function gstinUniqueRule(): Rule|string
    {
        return Rule::unique('customers', 'gstin');
    }
}
