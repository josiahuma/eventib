{{-- resources/views/payouts/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        @php
            // Prefer a $method passed from controller; fall back to relation
            $method = $method ?? ($event->payoutMethod ?? null);

            // Country-specific UI spec (labels/symbol) – keep your existing shape
            $spec   = $spec ?? [
                'country' => $currency ?? '—',
                'paypal'  => true,
                'symbol'  => '',
                'labels'  => [
                    'account_name'   => 'Account name',
                    'sort_code'      => 'Bank code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ];
            $labels = $spec['labels'];
            $symbol = $spec['symbol'] ?? '';

            $isBank = $method && $method->type === 'bank';
            $country = $isBank ? strtoupper($method->country ?? ($spec['country'] ?? '')) : ($spec['country'] ?? '');
        @endphp

        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Request payout — {{ $event->name }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            Payout method is locked to the one chosen for this event.
        </p>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
            <div class="mb-4 text-sm text-gray-600">
                Amount available:
                <span class="font-semibold">{{ $symbol }}{{ number_format($amount/100, 2) }}</span>
                <span class="ml-1 text-gray-500">({{ $currency }})</span>
            </div>

            @if(!$method)
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 text-sm">
                    This event doesn’t have a payout method selected (or it was removed).
                    <a class="underline" href="{{ route('profile.payouts') }}?country={{ urlencode($spec['country'] ?? '') }}" target="_blank">
                        Add / manage payout methods
                    </a> and then refresh this page.
                </div>
            @else
                <form method="POST" action="{{ route('payouts.store', $event) }}">
                    @csrf

                    {{-- Always post the amount + the locked payout method --}}
                    <input type="hidden" name="amount" value="{{ $amount }}">
                    <input type="hidden" name="method" value="{{ $method->type }}">
                    <input type="hidden" name="payout_method_id" value="{{ $method->id }}">
                    @if($isBank)
                        <input type="hidden" name="country" value="{{ $country }}">
                    @endif

                    {{-- Summary card --}}
                    <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="font-medium text-gray-900">
                            {{ $isBank ? "Bank transfer ($country)" : 'PayPal' }}
                        </div>
                        <div class="mt-1 text-sm text-gray-700">
                            @if($isBank)
                                {{ $method->account_name }}
                                — {{ $method->sort_code }} / {{ $method->account_number }}
                                @if(!empty($method->iban))
                                    <span class="block text-gray-500">IBAN/SWIFT: {{ $method->iban }}</span>
                                @endif
                            @else
                                {{ $method->paypal_email }}
                            @endif
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            To change payout details, edit them on the
                            <a class="underline" href="{{ route('profile.payouts') }}" target="_blank">Payout methods</a> page.
                        </div>
                    </div>

                    {{-- Read-only fields (so your validator still passes) --}}
                    @if($isBank)
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="account_name" :value="$labels['account_name']" />
                                <x-text-input id="account_name" name="account_name"
                                              class="block mt-1 w-full" readonly
                                              value="{{ $method->account_name }}" />
                                @error('account_name') <x-input-error :messages="$errors->get('account_name')" class="mt-2" /> @enderror
                            </div>

                            <div>
                                <x-input-label for="sort_code" :value="$labels['sort_code']" />
                                <x-text-input id="sort_code" name="sort_code"
                                              class="block mt-1 w-full" readonly
                                              value="{{ $method->sort_code }}" />
                                @error('sort_code') <x-input-error :messages="$errors->get('sort_code')" class="mt-2" /> @enderror
                            </div>

                            <div>
                                <x-input-label for="account_number" :value="$labels['account_number']" />
                                <x-text-input id="account_number" name="account_number"
                                              class="block mt-1 w-full" readonly
                                              value="{{ $method->account_number }}" />
                                @error('account_number') <x-input-error :messages="$errors->get('account_number')" class="mt-2" /> @enderror
                            </div>

                            <div>
                                <x-input-label for="iban" :value="$labels['iban']" />
                                <x-text-input id="iban" name="iban"
                                              class="block mt-1 w-full" readonly
                                              value="{{ $method->iban }}" />
                                @error('iban') <x-input-error :messages="$errors->get('iban')" class="mt-2" /> @enderror
                            </div>
                        </div>
                    @else
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="paypal_email" value="PayPal email" />
                                <x-text-input id="paypal_email" name="paypal_email" type="email"
                                              class="block mt-1 w-full" readonly
                                              value="{{ $method->paypal_email }}" />
                                @error('paypal_email') <x-input-error :messages="$errors->get('paypal_email')" class="mt-2" /> @enderror
                            </div>
                            <p class="text-xs text-gray-500">
                                Payout will be sent to this PayPal account in {{ $currency }}.
                            </p>
                        </div>
                    @endif

                    <div class="mt-6">
                        <x-primary-button>Submit payout request</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
