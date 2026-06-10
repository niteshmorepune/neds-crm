<x-app-layout>
    <x-slot name="header">{{ $task->title }}</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $task->title }}</h1>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">Project:</span> {{ $task->project?->name ?? 'Standalone' }}</div>
                        <div><span class="text-gray-400">Assignee:</span> {{ $task->assignee?->name ?? 'Unassigned' }}</div>
                        <div><span class="text-gray-400">Priority:</span> {{ $task->priority->label() }}</div>
                        <div><span class="text-gray-400">Status:</span> {{ $task->status->label() }}</div>
                        <div class="{{ $task->isOverdue() ? 'text-red-600' : '' }}"><span class="text-gray-400">Due:</span> {{ $task->due_date?->format('d M Y') ?? '—' }}{{ $task->isOverdue() ? ' (overdue)' : '' }}</div>
                    </dl>
                    @if ($task->description)
                        <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $task->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('tasks.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    @can('update', $task)
                        <a href="{{ route('tasks.edit', $task) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Edit</a>
                    @endcan
                </div>
            </div>

            @can('update', $task)
                <form method="POST" action="{{ route('tasks.status', $task) }}" class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-4">
                    @csrf @method('PATCH')
                    <label class="text-sm text-gray-500">Quick status:</label>
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm" onchange="this.form.submit()">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($task->status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </form>
            @endcan
        </div>

        {{-- Attachments --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Attachments</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($task->attachments as $attachment)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 hover:underline">{{ $attachment->original_name }}</a>
                        <span class="flex items-center gap-3 text-xs text-gray-400">
                            {{ $attachment->humanSize() }}
                            @can('update', $task)
                                <form method="POST" action="{{ route('attachments.destroy', $attachment) }}">@csrf @method('DELETE')
                                    <button class="text-red-600 hover:text-red-500">Remove</button>
                                </form>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No attachments.</li>
                @endforelse
            </ul>

            @can('update', $task)
                <form method="POST" action="{{ route('tasks.attachments.store', $task) }}" enctype="multipart/form-data" class="mt-4 flex items-center gap-2">
                    @csrf
                    <input type="file" name="file" required class="text-sm" />
                    <x-primary-button>Upload</x-primary-button>
                    @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </form>
            @endcan
        </div>

        {{-- Comments --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Comments</h2>
            <livewire:task-comments :task="$task" :can-manage="$canManage" />
        </div>
    </div>
</x-app-layout>
