<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit payout method
        </h2>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="form-card shadow-sm">
            <h3 class="form-section-title">
                {{ $method->type === 'paypal' ? 'PayPal payout' : 'Bank transfer details' }}
            </h3>
            <p class="mt-1 text-sm text-slate-600">
                Update the payout details used to send funds for your events.
            </p>

            <form method="POST"
                  action="{{ route('profile.payouts.update', $method) }}"
                  class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                @if ($method->type === 'paypal')
                    {{-- PayPal email --}}
                    <div>
                        <label class="form-label">PayPal email</label>
                        <input
                            type="email"
                            name="paypal_email"
                            class="form-input"
                            value="{{ old('paypal_email', $method->paypal_email) }}"
                            required
                        >
                        @error('paypal_email')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror

                        <p class="form-help">
                            Payouts for supported events will be sent to this PayPal account.
                        </p>
                    </div>
                @else
                    {{-- Country (locked) --}}
                    <div>
                        <label class="form-label">Country</label>
                        <input
                            type="text"
                            class="form-input bg-slate-100"
                            value="{{ $countryName }} ({{ $method->country }})"
                            disabled
                        >
                        <p class="form-help">
                            To change country, create a new payout method instead.
                        </p>
                    </div>

                    {{-- Account holder name --}}
                    <div>
                        <label class="form-label">Account holder name</label>
                        <input
                            type="text"
                            name="account_name"
                            class="form-input"
                            value="{{ old('account_name', $method->account_name) }}"
                            required
                        >
                        @error('account_name')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Sort code / routing + Account number --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Bank / routing / sort code</label>
                            <input
                                type="text"
                                name="sort_code"
                                class="form-input"
                                value="{{ old('sort_code', $method->sort_code) }}"
                                required
                            >
                            @error('sort_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label">Account number</label>
                            <input
                                type="text"
                                name="account_number"
                                class="form-input"
                                value="{{ old('account_number', $method->account_number) }}"
                                required
                            >
                            @error('account_number')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- IBAN / SWIFT optional --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">IBAN (optional)</label>
                            <input
                                type="text"
                                name="iban"
                                class="form-input"
                                value="{{ old('iban', $method->iban) }}"
                            >
                            @error('iban')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label">SWIFT (optional)</label>
                            <input
                                type="text"
                                name="swift"
                                class="form-input"
                                value="{{ old('swift', $method->swift) }}"
                            >
                            @error('swift')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                <div class="pt-2 flex items-center justify-between">
                    <a href="{{ route('profile.payouts') }}"
                       class="text-sm text-slate-600 hover:text-slate-900 underline">
                        Cancel
                    </a>

                    <button type="submit" class="form-primary-btn">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
