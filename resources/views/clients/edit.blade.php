<x-app-layout>
    <x-slot name="header">Edit Client</x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('clients.update', $customer) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('clients._form')

            <div class="mt-6 flex items-center justify-between gap-3">
                @can('delete', $customer)
                    <button type="submit"
                            form="delete-client"
                            class="text-sm font-medium text-red-600 hover:text-red-500"
                            onclick="return confirm('Delete this client? All related deals, quotations, invoices, projects, tasks, and tickets will also be removed. This action cannot be undone.')">
                        Delete client
                    </button>
                @else
                    <span></span>
                @endcan

                <div class="flex items-center gap-3">
                    <a href="{{ route('clients.show', $customer) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </div>
        </form>

        @can('delete', $customer)
            <form id="delete-client" method="POST" action="{{ route('clients.destroy', $customer) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </div>
</x-app-layout>
