<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via ProjectPolicy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => [Rule::exists('users', 'id')],
            'google_drive_folder_link' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
