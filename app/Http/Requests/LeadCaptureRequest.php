<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // token checked by VerifyLeadCaptureToken middleware
    }

    /**
     * Normalise field name aliases from Elementor / WPForms / CF7 / n8n before
     * validation runs. Maps common alternative keys to the canonical API keys.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // name aliases: full_name, full-name, your-name, contact_name
        if (! $this->has('name')) {
            $alias = $this->input('full_name')
                ?? $this->input('full-name')
                ?? $this->input('your-name')
                ?? $this->input('contact_name')
                ?? $this->input('contact-name');
            if ($alias !== null) {
                $merge['name'] = $alias;
            }
        }

        // phone aliases: contact_number, contact-number, whatsapp, whatsapp_no,
        //                whatsapp-no, mobile, mobile_number, phone_number
        if (! $this->has('phone')) {
            $alias = $this->input('contact_number')
                ?? $this->input('contact-number')
                ?? $this->input('whatsapp')
                ?? $this->input('whatsapp_no')
                ?? $this->input('whatsapp-no')
                ?? $this->input('mobile')
                ?? $this->input('mobile_number')
                ?? $this->input('phone_number');
            if ($alias !== null) {
                $merge['phone'] = $alias;
            }
        }

        // service aliases: search_engine_optimization → SEO, etc.
        // Also accept a "subject" or "enquiry_type" as the service name.
        if (! $this->has('service') && ! $this->has('service_id')) {
            $alias = $this->input('subject')
                ?? $this->input('enquiry_type')
                ?? $this->input('enquiry-type')
                ?? $this->input('service_name')
                ?? $this->input('service-name');
            if ($alias !== null) {
                $merge['service'] = $alias;
            }
        }

        // message aliases: requirement, your-message, your_message, description
        if (! $this->has('message')) {
            $alias = $this->input('requirement')
                ?? $this->input('your-message')
                ?? $this->input('your_message')
                ?? $this->input('description')
                ?? $this->input('enquiry')
                ?? $this->input('details');
            if ($alias !== null) {
                $merge['message'] = $alias;
            }
        }

        // company aliases: company_name, company-name, organization, organisation
        if (! $this->has('company')) {
            $alias = $this->input('company_name')
                ?? $this->input('company-name')
                ?? $this->input('organization')
                ?? $this->input('organisation');
            if ($alias !== null) {
                $merge['company'] = $alias;
            }
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'service'    => ['nullable', 'string', 'max:255'], // name from website dropdown; resolved to service_id in controller
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
