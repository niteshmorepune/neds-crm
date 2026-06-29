<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="title" value="Title *" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                      :value="old('title', $contentPiece->title ?? '')" required />
        <x-input-error :messages="$errors->get('title')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="workflow_type" value="Workflow type *" />
        <select id="workflow_type" name="workflow_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach (App\Enums\ContentWorkflowType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('workflow_type', $contentPiece->workflow_type?->value ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('workflow_type')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="platform" value="Platform *" />
        <select id="platform" name="platform" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach (App\Enums\ContentPlatform::cases() as $platform)
                <option value="{{ $platform->value }}" @selected(old('platform', $contentPiece->platform?->value ?? '') === $platform->value)>{{ $platform->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('platform')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="partner_id" value="Partner (agency)" />
        <select id="partner_id" name="partner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">— No partner —</option>
            @foreach ($partners as $partner)
                <option value="{{ $partner->id }}" @selected((string) old('partner_id', $contentPiece->partner_id ?? '') === (string) $partner->id)>{{ $partner->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="publish_date" value="Publish date" />
        <x-text-input id="publish_date" name="publish_date" type="date" class="mt-1 block w-full"
                      :value="old('publish_date', isset($contentPiece->publish_date) ? $contentPiece->publish_date->toDateString() : '')" />
        <x-input-error :messages="$errors->get('publish_date')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="google_drive_link" value="Google Drive link (specific file)" />
        <x-text-input id="google_drive_link" name="google_drive_link" type="url" class="mt-1 block w-full"
                      :value="old('google_drive_link', $contentPiece->google_drive_link ?? '')"
                      placeholder="https://drive.google.com/file/..." />
        <p class="mt-1 text-xs text-gray-400">Link to the specific content file on Drive (if sharing via Drive instead of or in addition to upload link).</p>
        <x-input-error :messages="$errors->get('google_drive_link')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="copy_text" value="Copy / Brief" />
        <textarea id="copy_text" name="copy_text" rows="5"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="Write the caption, script, or creative brief here...">{{ old('copy_text', $contentPiece->copy_text ?? '') }}</textarea>
        <p class="mt-1 text-xs text-gray-400">For NEDS-led workflow: the writeup/brief you send to the partner to create visuals.</p>
        <x-input-error :messages="$errors->get('copy_text')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="notes" value="Internal notes" />
        <textarea id="notes" name="notes" rows="2"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $contentPiece->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>
</div>
