@php($user = $user ?? auth()->user())

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Two-Factor Authentication</h2>
        <p class="mt-1 text-sm text-gray-600">
            Add an extra layer of security using an authenticator app (Google Authenticator, Authy, 1Password…).
            @if ($user->requiresTwoFactor())
                <span class="font-medium text-amber-700">Required for your role.</span>
            @endif
        </p>
    </header>

    @if (session('status') === 'two-factor-required')
        <div class="mt-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            Your role requires two-factor authentication. Please set it up to continue.
        </div>
    @endif

    {{-- Newly generated recovery codes, shown once. --}}
    @if (session('recovery_codes'))
        <div class="mt-4 rounded-md bg-gray-50 border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-800">Recovery codes</p>
            <p class="text-xs text-gray-500">Store these somewhere safe. Each can be used once if you lose your device.</p>
            <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm text-gray-700">
                @foreach (session('recovery_codes') as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6">
        @if ($user->hasTwoFactorEnabled())
            {{-- Enabled --}}
            <p class="inline-flex items-center gap-2 text-sm font-medium text-green-700">
                <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span> Two-factor is enabled.
            </p>
            <div class="mt-4 flex items-center gap-3">
                <form method="POST" action="{{ route('two-factor.recovery') }}">
                    @csrf
                    <x-secondary-button>Regenerate recovery codes</x-secondary-button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf @method('DELETE')
                    <x-danger-button>Disable</x-danger-button>
                </form>
            </div>
        @elseif ($twoFactorQr ?? null)
            {{-- Secret generated, awaiting confirmation --}}
            <p class="text-sm text-gray-600">Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.</p>
            <div class="mt-3 inline-block rounded-md border border-gray-200 p-3">{!! $twoFactorQr !!}</div>
            <p class="mt-2 text-xs text-gray-500">Can't scan? Enter this key manually: <span class="font-mono">{{ $user->two_factor_secret }}</span></p>

            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex items-end gap-2">
                @csrf
                <div>
                    <x-input-label for="code" value="Verification code" />
                    <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-40" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
                <x-primary-button>Confirm</x-primary-button>
            </form>

            <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-2">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Cancel setup</button>
            </form>
        @else
            {{-- Not enabled --}}
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <x-primary-button>Enable two-factor</x-primary-button>
            </form>
        @endif
    </div>
</section>
