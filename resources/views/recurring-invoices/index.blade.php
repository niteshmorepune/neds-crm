<x-app-layout>
    <x-slot name="header">Recurring Invoices</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</a>
            <a href="{{ route('recurring-invoices.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Recurring</a>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Frequency</th>
                        <th class="px-4 py-3">Next run</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recurring as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-700">{{ $r->customer?->company_name ?? 'Client removed' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $r->frequency->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $r->next_run_on->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $r->is_active,
                                    'bg-gray-100 text-gray-600' => ! $r->is_active,
                                ])>{{ $r->is_active ? 'Active' : 'Paused' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('recurring-invoices.show', $r) }}" class="text-indigo-600 hover:text-indigo-500">Invoices</a>
                                <a href="{{ route('recurring-invoices.edit', $r) }}" class="ml-3 text-gray-500 hover:text-gray-700">Edit</a>
                                <form method="POST" action="{{ route('recurring-invoices.toggle', $r) }}" class="inline">
                                    @csrf @method('PUT')
                                    <button class="ml-3 text-gray-500 hover:text-gray-700">{{ $r->is_active ? 'Pause' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No recurring invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $recurring->links() }}</div>
    </div>
</x-app-layout>
