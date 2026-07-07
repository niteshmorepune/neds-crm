<x-app-layout>
    <x-slot name="header">Partners</x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-end">
            @can('create', App\Models\Partner::class)
                <a href="{{ route('partners.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ New Partner</a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3 text-right">Content Pieces</th>
                        <th class="px-4 py-3 text-right">Referred Clients</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($partners as $partner)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $partner->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $partner->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $partner->phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $partner->contentPieces->count() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('clients.index', ['referring_partner_id' => $partner->id, 'status' => 'all']) }}"
                                   class="text-indigo-600 hover:underline">{{ $partner->referredCustomers->count() }}</a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $partner)
                                    <a href="{{ route('partners.edit', $partner) }}" class="text-indigo-600 hover:underline">Edit</a>
                                @endcan
                                @can('delete', $partner)
                                    <form method="POST" action="{{ route('partners.destroy', $partner) }}" class="inline ml-3"
                                          onsubmit="return confirm('Remove this partner? Content pieces will not be deleted.')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-500">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No partners yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
