<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeLeadsRequest extends FormRequest
{
    /**
     * Every field the merge review screen lets the user pick a source for.
     *
     * @var list<string>
     */
    public const MERGEABLE_FIELDS = [
        'name', 'company', 'phone', 'email', 'source', 'service_id', 'estimated_value', 'owner_id', 'status',
    ];

    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'primary_id' => ['required', 'integer', 'different:duplicate_id', 'exists:leads,id'],
            'duplicate_id' => ['required', 'integer', 'exists:leads,id'],
            'field_source' => ['required', 'array', 'required_array_keys:'.implode(',', self::MERGEABLE_FIELDS)],
            // Each field's chosen value must be one of the two leads actually
            // being merged — never an arbitrary id — computed per-request
            // since the allowed pair depends on primary_id/duplicate_id.
            'field_source.*' => ['required', Rule::in([$this->input('primary_id'), $this->input('duplicate_id')])],
        ];
    }
}
