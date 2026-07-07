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
     * Normalise incoming fields before validation.
     *
     * Strategy (applied in order):
     * 1. Explicit aliases — map known alternative key names to canonical fields.
     * 2. Heuristic scan — if canonical fields are still missing, walk all
     *    remaining (non-system) fields and classify by value shape:
     *    email-shaped → email, digit-heavy → phone, plain string → name.
     * 3. Dump all unrecognised fields into the message note so nothing is lost.
     * 4. Final fallback — name defaults to "Website Inquiry" so validation never
     *    fails on a real form submission.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // ── 0. Strip Elementor's "No_Label_" key prefix ─────────────────────
        // Elementor sends field IDs as-is. If the field label wasn't set,
        // Elementor prefixes each key with "No_Label_" (e.g. No_Label_name,
        // No_Label_phone). Strip the prefix so subsequent alias + heuristic
        // logic sees clean keys.
        $stripped = [];
        foreach ($this->all() as $key => $value) {
            if (str_starts_with((string) $key, 'No_Label_')) {
                $stripped[substr($key, 9)] = $value;
            }
        }
        if ($stripped) {
            $this->merge($stripped);
        }

        // ── 1. Explicit aliases ──────────────────────────────────────────────

        if (! $this->has('name')) {
            $v = $this->input('full_name')
                ?? $this->input('full-name')
                ?? $this->input('your-name')
                ?? $this->input('contact_name')
                ?? $this->input('contact-name')
                ?? $this->input('your_name');
            if ($v !== null) {
                $merge['name'] = $v;
            }
        }

        if (! $this->has('phone')) {
            $v = $this->input('contact_number')
                ?? $this->input('contact-number')
                ?? $this->input('whatsapp')
                ?? $this->input('whatsapp_no')
                ?? $this->input('whatsapp-no')
                ?? $this->input('mobile')
                ?? $this->input('mobile_number')
                ?? $this->input('mobile-number')
                ?? $this->input('phone_number')
                ?? $this->input('phone-number');
            if ($v !== null) {
                $merge['phone'] = $v;
            }
        }

        if (! $this->has('service') && ! $this->has('service_id')) {
            $v = $this->input('subject')
                ?? $this->input('enquiry_type')
                ?? $this->input('enquiry-type')
                ?? $this->input('service_name')
                ?? $this->input('service-name');
            if ($v !== null) {
                $merge['service'] = $v;
            }
        }

        if (! $this->has('message')) {
            $v = $this->input('requirement')
                ?? $this->input('your-message')
                ?? $this->input('your_message')
                ?? $this->input('description')
                ?? $this->input('enquiry')
                ?? $this->input('details')
                ?? $this->input('comments')
                ?? $this->input('comment');
            if ($v !== null) {
                $merge['message'] = $v;
            }
        }

        if (! $this->has('company')) {
            $v = $this->input('company_name')
                ?? $this->input('company-name')
                ?? $this->input('organization')
                ?? $this->input('organisation');
            if ($v !== null) {
                $merge['company'] = $v;
            }
        }

        if (! $this->has('utm_source')) {
            $v = $this->input('utm-source');
            if ($v !== null) {
                $merge['utm_source'] = $v;
            }
        }

        if (! $this->has('utm_medium')) {
            $v = $this->input('utm-medium');
            if ($v !== null) {
                $merge['utm_medium'] = $v;
            }
        }

        if (! $this->has('utm_campaign')) {
            $v = $this->input('utm-campaign');
            if ($v !== null) {
                $merge['utm_campaign'] = $v;
            }
        }

        // ── 2. Heuristic scan of unknown fields ─────────────────────────────
        // Fields that are either standard API fields or Elementor/form system fields.
        $knownKeys = [
            'name', 'email', 'phone', 'company', 'service', 'service_id',
            'estimated_value', 'message', 'token',
            'utm_source', 'utm_medium', 'utm_campaign',
            'utm-source', 'utm-medium', 'utm-campaign',
            // Elementor webhook system fields
            'form_id', 'form_name', 'referer', 'queried_id', 'post_id',
            'remote_ip', 'submitted_on',
        ];

        $unknown = collect($this->except($knownKeys))
            ->filter(fn ($v) => is_string($v) && trim($v) !== '');

        if ($unknown->isNotEmpty()) {
            foreach ($unknown as $val) {
                $val = trim($val);

                // Email-shaped value
                if (! $this->has('email') && ! isset($merge['email'])
                    && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $merge['email'] = $val;

                    continue;
                }

                // Phone-shaped value (7–15 chars, mostly digits/spaces/+/-)
                if (! $this->has('phone') && ! isset($merge['phone'])
                    && preg_match('/^[\d\s\+\-\(\)]{7,15}$/', $val)) {
                    $merge['phone'] = $val;

                    continue;
                }

                // First plain string → name
                if (! $this->has('name') && ! isset($merge['name'])
                    && strlen($val) <= 255) {
                    $merge['name'] = $val;

                    continue;
                }
            }

            // Append all unknown fields to message so nothing is lost.
            $rawLines = $unknown->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
            $existing = $merge['message'] ?? $this->input('message', '');
            $merge['message'] = $existing
                ? $existing."\n\n[Form fields]\n".$rawLines
                : "[Form fields]\n".$rawLines;
        }

        // ── 3. Final fallback ────────────────────────────────────────────────
        // Ensure name is never empty so a real form submission always creates a
        // lead rather than returning 422 to the form plugin (which shows an error).
        if (! $this->has('name') && empty($merge['name'])) {
            $merge['name'] = 'Website Inquiry';
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'service' => ['nullable', 'string', 'max:255'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'message' => ['nullable', 'string', 'max:5000'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ];
    }
}
