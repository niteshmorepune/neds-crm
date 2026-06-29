<?php

namespace App\Http\Requests;

use App\Enums\ContentPlatform;
use App\Enums\ContentWorkflowType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_type' => ['sometimes', Rule::enum(ContentWorkflowType::class)],
            'platform' => ['sometimes', Rule::enum(ContentPlatform::class)],
            'title' => ['sometimes', 'string', 'max:255'],
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'copy_text' => ['nullable', 'string'],
            'google_drive_link' => ['nullable', 'url', 'max:2048'],
            'publish_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
