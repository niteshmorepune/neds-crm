<x-app-layout>
    <x-slot name="header">Team Daily Reports</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-md border-gray-300 text-sm shadow-sm" onchange="this.form.submit()" />
            <a href="{{ route('daily-reports.index') }}" class="text-sm text-indigo-600 hover:underline">My report</a>
        </form>

        <div class="space-y-3">
            @foreach ($users as $person)
                @php($r = $reports->get($person->id))
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="font-medium text-gray-900">{{ $person->name }}</div>
                        @if ($r)
                            <div class="text-xs text-gray-500">
                                {{ $r->tasks_completed }} tasks Â· {{ $r->calls_made }} calls Â· {{ $r->leads_touched }} leads
                                Â· submitted {{ $r->submitted_at?->timezone(config('app.display_timezone'))->format('g:i A') }}
                            </div>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Not submitted</span>
                        @endif
                    </div>
                    @if ($r && $r->summary)
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $r->summary }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>

