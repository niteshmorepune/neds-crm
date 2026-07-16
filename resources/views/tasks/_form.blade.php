<div class="grid grid-cols-1 gap-6 md:grid-cols-2"
     x-data="{
        projectId: {{ Js::from((string) old('project_id', $task->project_id ?? '')) }},
        selectedMilestone: {{ Js::from((string) old('milestone_id', $task->milestone_id ?? '')) }},
        milestonesByProject: {{ Js::from($milestonesByProject) }},
        get milestones() { return this.milestonesByProject[this.projectId] || [] },
     }"
     x-init="$watch('projectId', () => { if (! milestones.some((m) => String(m.id) === selectedMilestone)) selectedMilestone = '' })">
    <div class="md:col-span-2">
        <x-input-label for="title" value="Title *" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $task->title)" required />
        <x-input-error :messages="$errors->get('title')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="project_id" value="Project" />
        <select id="project_id" name="project_id" x-model="projectId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Standalone</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((string) old('project_id', $task->project_id) === (string) $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="milestones.length > 0" x-cloak>
        <x-input-label for="milestone_id" value="Milestone" />
        <select id="milestone_id" name="milestone_id" x-model="selectedMilestone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">— None —</option>
            <template x-for="m in milestones" :key="m.id">
                <option :value="m.id" x-text="m.title"></option>
            </template>
        </select>
        <p class="mt-1 text-xs text-gray-400">Once every task linked to a milestone is Done, it'll suggest marking the milestone Done.</p>
        <x-input-error :messages="$errors->get('milestone_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="assignee_id" value="Assignee" />
        <select id="assignee_id" name="assignee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Unassigned</option>
            @foreach ($staff as $person)
                <option value="{{ $person->id }}" @selected((string) old('assignee_id', $task->assignee_id) === (string) $person->id)>{{ $person->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="priority" value="Priority *" />
        <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected(old('priority', $task->priority?->value) === $priority->value)>{{ $priority->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="status" value="Status *" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $task->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="due_date" value="Due date" />
        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $task->due_date?->toDateString())" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $task->description) }}</textarea>
    </div>
</div>
