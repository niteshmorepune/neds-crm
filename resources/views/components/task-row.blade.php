@props(['task', 'taskStatuses', 'muted' => false])

<tr class="hover:bg-gray-50">
    <td class="px-4 py-2">
        <a href="{{ route('tasks.show', $task) }}" @class([
            'font-medium hover:underline',
            'text-gray-500' => $muted,
            'text-indigo-600' => ! $muted,
        ])>{{ $task->title }}</a>
    </td>
    <td class="px-4 py-2 text-gray-600">{{ $task->status->label() }}</td>
    <td class="px-4 py-2 text-gray-600">{{ $task->priority->label() }}</td>
    <td class="px-4 py-2 {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-gray-600' }}">
        {{ $task->due_date?->format('d M Y') ?? '—' }}
    </td>
    <td class="px-4 py-2">
        <form method="POST" action="{{ route('tasks.status', $task) }}">
            @csrf @method('PATCH')
            <select name="status" class="rounded-md border-gray-300 text-xs shadow-sm" onchange="this.form.submit()">
                @foreach ($taskStatuses as $s)
                    <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </form>
    </td>
</tr>
