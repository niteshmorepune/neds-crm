<div>
    <x-input-label for="title" value="Title *" />
    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $nudge->title ?? '')" required />
    <x-input-error :messages="$errors->get('title')" class="mt-1" />
</div>

<div>
    <x-input-label for="description" value="Description" />
    <textarea id="description" name="description" rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $nudge->description ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-1" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="target_role" value="Target *" />
        <select id="target_role" name="target_role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="" @selected(old('target_role', $nudge->target_role?->value ?? '') === '')>Everyone</option>
            @foreach (\App\Enums\UserRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(old('target_role', $nudge->target_role?->value ?? '') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('target_role')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="recurrence" value="Recurrence *" />
        <select id="recurrence" name="recurrence" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            @foreach (\App\Enums\NudgeRecurrence::cases() as $option)
                <option value="{{ $option->value }}" @selected(old('recurrence', $nudge->recurrence->value ?? 'one_time') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('recurrence')" class="mt-1" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="auto_detect_type" value="Auto-detect (weekly only)" />
        <select id="auto_detect_type" name="auto_detect_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="" @selected(old('auto_detect_type', $nudge->auto_detect_type?->value ?? '') === '')>None — manual only</option>
            @foreach (\App\Enums\NudgeAutoDetectType::cases() as $option)
                <option value="{{ $option->value }}" @selected(old('auto_detect_type', $nudge->auto_detect_type?->value ?? '') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Auto-clears the current week's row the moment the check is true — only valid when Recurrence is Weekly.</p>
        <x-input-error :messages="$errors->get('auto_detect_type')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="due_date" value="Due date (one-time only)" />
        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full"
            :value="old('due_date', isset($nudge) && $nudge->due_date ? $nudge->due_date->format('Y-m-d') : '')" />
        <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
    </div>
</div>

<label class="inline-flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $nudge->is_active ?? true)) class="rounded border-gray-300 text-indigo-600" />
    <span class="text-sm text-gray-700">Active</span>
</label>
