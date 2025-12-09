<x-guest-layout>
    <div class="min-h-[70vh] flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8">

                {{-- Header --}}
                <h1 class="text-xl font-semibold text-gray-900 mb-2">Forgot your password?</h1>
                <p class="text-sm text-gray-600 mb-6">
                    No problem — enter your email and we’ll send you a reset link.
                </p>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                {{-- Form --}}
                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <div class="relative mt-1">
                            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"
                                 viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2 6.75A2.75 2.75 0 0 1 4.75 4h14.5A2.75 2.75 0 0 1 22 6.75v10.5A2.75 2.75 0 0 1 19.25 20H4.75A2.75 2.75 0 0 1 2 17.25V6.75zm2.4-.25L12 11.1l7.6-4.6H4.4z"/>
                            </svg>

                            <x-text-input
                                id="email"
                                type="email"
                                name="email"
                                :value="old('email')"
                                required
                                autofocus
                                autocomplete="email"
                                class="form-input block w-full rounded-none border-gray-300 pl-10
                                       focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    {{-- Submit --}}
                    <x-primary-button class="w-full justify-center rounded-none py-2.5">
                        {{ __('Email Password Reset Link') }}
                    </x-primary-button>
                </form>

            </div>
        </div>
    </div>
</x-guest-layout>
