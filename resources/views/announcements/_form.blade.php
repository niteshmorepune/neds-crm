<div>
    <x-input-label for="title" value="Title *" />
    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $announcement->title ?? '')" required />
    <x-input-error :messages="$errors->get('title')" class="mt-1" />
</div>

<div>
    <x-input-label for="body" value="Message *" />
    <textarea id="body" name="body" rows="4" required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body', $announcement->body ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('body')" class="mt-1" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="audience" value="Audience *" />
        <select id="audience" name="audience" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            @foreach (\App\Enums\AnnouncementAudience::cases() as $option)
                <option value="{{ $option->value }}" @selected(old('audience', $announcement->audience->value ?? 'both') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('audience')" class="mt-1" />
    </div>

    <div class="flex items-end pb-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned ?? false)) class="rounded border-gray-300 text-indigo-600" />
            <span class="text-sm text-gray-700">Pin to top</span>
        </label>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="starts_at" value="Starts *" />
        <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
            :value="old('starts_at', isset($announcement) ? $announcement->starts_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'))" required />
        <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="ends_at" value="Ends" />
        <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
            :value="old('ends_at', isset($announcement) && $announcement->ends_at ? $announcement->ends_at->format('Y-m-d\TH:i') : '')" />
        <p class="mt-1 text-xs text-gray-400">Leave blank for a standing notice with no expiry.</p>
        <x-input-error :messages="$errors->get('ends_at')" class="mt-1" />
    </div>
</div>
