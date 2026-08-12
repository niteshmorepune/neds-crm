<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(AnnouncementAudience::values())],
            'is_pinned' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * The starts_at/ends_at inputs are `datetime-local` values typed against
     * Asia/Kolkata (what the admin sees on screen), but app.timezone is UTC —
     * parse them against the display timezone before they hit the model's
     * plain `datetime` cast, same fix as MeetingImport::createMeeting().
     */
    protected function prepareForValidation(): void
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');

        $this->merge([
            'is_pinned' => $this->boolean('is_pinned'),
            'starts_at' => $this->filled('starts_at')
                ? Carbon::createFromFormat('Y-m-d\TH:i', $this->input('starts_at'), $tz)->utc()->toDateTimeString()
                : $this->input('starts_at'),
            'ends_at' => $this->filled('ends_at')
                ? Carbon::createFromFormat('Y-m-d\TH:i', $this->input('ends_at'), $tz)->utc()->toDateTimeString()
                : null,
        ]);
    }
}
