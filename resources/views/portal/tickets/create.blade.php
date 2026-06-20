<x-portal-app-layout header="Raise a Ticket">
    @if (config('company.whatsapp'))
    <div class="mb-6 flex items-center justify-between gap-4 rounded-xl bg-green-50 border border-green-200 px-5 py-3.5">
        <p class="text-sm text-green-800 font-medium">For urgent issues — get a faster response on WhatsApp.</p>
        <x-whatsapp-button label="Chat now" message="Hi, I have an urgent support query." class="shrink-0 text-xs" />
    </div>
    @endif

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('portal.tickets.store') }}"
              class="rounded-xl bg-white px-6 py-6 shadow-sm ring-1 ring-gray-100 space-y-5">
            @csrf
            <div>
                <x-input-label for="subject" value="Subject *" />
                <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full"
                              :value="old('subject')" placeholder="e.g. Logo not showing on website" required />
                <x-input-error :messages="$errors->get('subject')" class="mt-1" />
            </div>
            @if($projects->isNotEmpty())
            <div>
                <x-input-label for="project_id" value="Which service is this about?" />
                <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">General / Not sure</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                            {{ $project->name }}{{ $project->service ? ' — '.$project->service->name : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Selecting a service routes your ticket to the right team member.</p>
                <x-input-error :messages="$errors->get('project_id')" class="mt-1" />
            </div>
            @endif

            <div>
                <x-input-label for="priority" value="Priority *" />
                <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Choose "High" or "Critical" only for issues that block your business.</p>
            </div>
            <div>
                <x-input-label for="description" value="Describe the issue *" />
                <textarea id="description" name="description" rows="5"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                          placeholder="Please describe the issue in detail — steps to reproduce, screenshots, etc."
                          required>{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>
            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('portal.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Submit Ticket</x-primary-button>
            </div>
        </form>

        <p class="mt-4 text-xs text-center text-gray-400">
            Our team typically responds within 4 business hours · For urgent issues use WhatsApp or call us.
        </p>
    </div>
</x-portal-app-layout>
