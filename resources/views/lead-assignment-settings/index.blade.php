<x-app-layout>
    <x-slot name="header">Force Lead Assignment</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Send every new lead to one Sales rep</h2>
            <p class="mt-1 text-xs text-gray-400">
                Turning this on forces the owner of <strong>every new lead, unconditionally</strong> to the rep
                chosen below — ahead of Lead Assignment Rules and the normal least-loaded round-robin — until an
                Admin or Manager turns it back off. It only affects leads created while this is on; leads already
                assigned keep their current owner.
            </p>

            <div class="mt-4 flex items-center gap-3">
                @if ($setting->enabled)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                        On — forcing to {{ $setting->forcedUser?->name ?? 'a removed user' }}
                    </span>
                    <span class="text-xs text-gray-500">
                        @if ($setting->updatedBy)
                            Enabled by {{ $setting->updatedBy->name }}, {{ $setting->updated_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') }}
                        @endif
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">Off — normal assignment</span>
                    <span class="text-xs text-gray-500">
                        @if ($setting->updatedBy)
                            Turned off by {{ $setting->updatedBy->name }}, {{ $setting->updated_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') }}
                        @endif
                    </span>
                @endif
            </div>

            <div class="mt-4">
                @if ($setting->enabled)
                    <form method="POST" action="{{ route('lead-assignment-settings.disable') }}">
                        @csrf
                        <x-danger-button>Turn off — resume normal assignment</x-danger-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('lead-assignment-settings.enable') }}" class="space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="forced_user_id" value="Assign every new lead to *" />
                            <select id="forced_user_id" name="forced_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                <option value="">—</option>
                                @foreach ($salesUsers as $salesUser)
                                    <option value="{{ $salesUser->id }}" @selected((string) old('forced_user_id', $setting->forced_user_id) === (string) $salesUser->id)>{{ $salesUser->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Only active Sales users can be chosen.</p>
                            <x-input-error :messages="$errors->get('forced_user_id')" class="mt-1" />
                        </div>
                        <x-primary-button>Turn on</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
