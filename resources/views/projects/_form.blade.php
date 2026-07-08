@php($selectedAssignees = collect(old('assignees', $project->exists ? $project->assignees->pluck('id')->all() : []))->map(fn ($i) => (string) $i))

<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="name" value="Project name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="customer_id" value="Client *" />
        <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Select client</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $project->customer_id) === (string) $customer->id)>{{ $customer->company_name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="service_id" value="Service" />
        <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((string) old('service_id', $project->service_id) === (string) $service->id)>{{ $service->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="owner_id" value="Project Manager" />
        <select id="owner_id" name="owner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach ($staff as $person)
                <option value="{{ $person->id }}" @selected((string) old('owner_id', $project->owner_id) === (string) $person->id)>{{ $person->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="status" value="Status *" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $project->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="start_date" value="Start date" />
        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $project->start_date?->toDateString())" />
    </div>

    <div>
        <x-input-label for="end_date" value="End date" />
        <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $project->end_date?->toDateString())" />
        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="assignees" value="Assignees" />
        <select id="assignees" name="assignees[]" multiple size="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($staff as $person)
                <option value="{{ $person->id }}" @selected($selectedAssignees->contains((string) $person->id))>{{ $person->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Hold Ctrl/Cmd to select multiple.</p>
    </div>

    <div class="md:col-span-2">
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $project->description) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <x-input-label for="google_drive_folder_link" value="Google Drive Folder Link" />
        <x-text-input id="google_drive_folder_link" name="google_drive_folder_link" type="url" class="mt-1 block w-full"
                      :value="old('google_drive_folder_link', $project->google_drive_folder_link)"
                      placeholder="https://drive.google.com/drive/folders/..." />
        <p class="mt-1 text-xs text-gray-400">Shared folder where the partner uploads/shares content for this project.</p>
        <x-input-error :messages="$errors->get('google_drive_folder_link')" class="mt-1" />
    </div>
</div>
