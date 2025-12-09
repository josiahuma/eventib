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
            $labels  = $spec['labels'];
            $symbol  = $spec['symbol'] ?? '';
            $isBank  = $method && $method->type === 'bank';
            $country = $isBank
                ? strtoupper($method->country ?? ($spec['country'] ?? ''))
                : ($spec['country'] ?? '');
        @endphp

        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Request payout — {{ $event->name }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            Payout method is locked to the one chosen for this event.
        </p>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Error summary --}}
        @if ($errors->any())
            <div class="mb-6 border border-rose-200 bg-rose-50 text-rose-800 rounded-xl p-4">
                <div class="font-semibold mb-1">Please fix the following:</div>
                <ul class="list-disc ms-5 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-card">
            <div class="flex items-center justify-between gap-4">
                <h3 class="form-section-title">Payout details</h3>
            </div>

            <p class="mt-2 text-sm text-slate-600">
                Once submitted, your payout request will be reviewed and processed to the payout method below.
            </p>

            <div class="mt-4 text-sm text-slate-700">
                <span class="font-medium">Amount available:</span>
                <span class="ml-1 font-semibold">
                    {{ $symbol }}{{ number_format($amount/100, 2) }}
                </span>
                <span class="ml-1 text-slate-500">({{ $currency }})</span>
            </div>

            @if(!$method)
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 text-sm">
                    This event doesn’t have a payout method selected (or it was removed).
                    <a class="underline font-medium"
                       href="{{ route('profile.payouts') }}?country={{ urlencode($spec['country'] ?? '') }}"
                       target="_blank">
                        Add / manage payout methods
                    </a>
                    and then refresh this page.
                </div>
            @else
                <form method="POST" action="{{ route('payouts.store', $event) }}" class="mt-6 space-y-5">
                    @csrf

                    {{-- Always post the amount + the locked payout method --}}
                    <input type="hidden" name="amount" value="{{ $amount }}">
                    <input type="hidden" name="method" value="{{ $method->type }}">
                    <input type="hidden" name="payout_method_id" value="{{ $method->id }}">
                    @if($isBank)
                        <input type="hidden" name="country" value="{{ $country }}">
                    @endif

                    {{-- Summary card --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="font-medium text-slate-900">
                            {{ $isBank ? "Bank transfer ($country)" : 'PayPal' }}
                        </div>
                        <div class="mt-1 text-sm text-slate-700">
                            @if($isBank)
                                {{ $method->account_name }}
                                — {{ $method->sort_code }} / {{ $method->account_number }}
                                @if(!empty($method->iban))
                                    <span class="block text-slate-500">
                                        IBAN/SWIFT: {{ $method->iban }}
                                    </span>
                                @endif
                            @else
                                {{ $method->paypal_email }}
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            To change payout details, edit them on the
                            <a class="underline" href="{{ route('profile.payouts') }}" target="_blank">
                                Payout methods
                            </a> page.
                        </p>
                    </div>

                    {{-- Read-only fields (kept so your validator still passes) --}}
                    @if($isBank)
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <label class="form-label">{{ $labels['account_name'] }}</label>
                                <input
                                    id="account_name"
                                    name="account_name"
                                    class="form-input"
                                    readonly
                                    value="{{ $method->account_name }}"
                                >
                                @error('account_name')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label">{{ $labels['sort_code'] }}</label>
                                <input
                                    id="sort_code"
                                    name="sort_code"
                                    class="form-input"
                                    readonly
                                    value="{{ $method->sort_code }}"
                                >
                                @error('sort_code')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label">{{ $labels['account_number'] }}</label>
                                <input
                                    id="account_number"
                                    name="account_number"
                                    class="form-input"
                                    readonly
                                    value="{{ $method->account_number }}"
                                >
                                @error('account_number')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label">{{ $labels['iban'] }}</label>
                                <input
                                    id="iban"
                                    name="iban"
                                    class="form-input"
                                    readonly
                                    value="{{ $method->iban }}"
                                >
                                @error('iban')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            <div>
                                <label class="form-label">PayPal email</label>
                                <input
                                    id="paypal_email"
                                    name="paypal_email"
                                    type="email"
                                    class="form-input"
                                    readonly
                                    value="{{ $method->paypal_email }}"
                                >
                                @error('paypal_email')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <p class="form-help">
                                Payout will be sent to this PayPal account in {{ $currency }}.
                            </p>
                        </div>
                    @endif

                    <div class="pt-4 flex items-center justify-end">
                        <button type="submit" class="form-primary-btn">
                            Submit payout request
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
