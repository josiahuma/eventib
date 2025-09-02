{{-- resources/views/profile/payouts.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">Payout methods</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto p-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-green-700">
                {{ session('status') }}
            </div>
        @endif

        @php
            $hasBank   = $methods->contains(fn($m) => $m->type === 'bank');
            $hasPaypal = $methods->contains(fn($m) => $m->type === 'paypal');
        @endphp

        {{-- Existing methods --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">Your methods</h3>

            @forelse($methods as $m)
                <div class="flex items-center justify-between py-2 border-t first:border-0">
                    <div class="text-sm">
                        <div class="font-medium">
                            {{ strtoupper($m->type) }} · {{ $m->type === 'bank' ? $m->country : 'PayPal' }}
                        </div>
                        <div class="text-gray-600">
                            @if ($m->type === 'bank')
                                {{ $m->account_name }} — {{ $m->sort_code }} / {{ $m->account_number }}
                            @else
                                {{ $m->paypal_email }}
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('profile.payouts.edit', $m) }}"
                           class="text-sm text-indigo-600 hover:text-indigo-700">Edit</a>

                        <form method="POST" action="{{ route('profile.payouts.destroy', $m) }}">
                            @csrf @method('DELETE')
                            <button class="text-sm text-rose-600 hover:text-rose-700">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-600">No payout methods yet.</p>
            @endforelse
        </div>

        {{-- Add new --}}
        <div
            class="bg-white rounded-xl border border-gray-200 p-4"
            x-data="{
                // defaults: if you already have a bank, start on paypal (if available), else bank
                type: '{{ $hasBank ? ($hasPaypal ? 'none' : 'paypal') : 'bank' }}',
                country: '{{ $prefill ?: 'GB' }}',
                hasBank: {{ $hasBank ? 'true' : 'false' }},
                hasPaypal: {{ $hasPaypal ? 'true' : 'false' }},
            }"
        >
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Add payout method</h3>

                {{-- Limit pill for bank --}}
                <template x-if="hasBank">
                    <span class="text-xs rounded-full bg-gray-100 text-gray-700 px-3 py-1">
                        Bank payout limit: <strong>1 of 1</strong>
                    </span>
                </template>
            </div>

            {{-- All filled? (nothing to add) --}}
            <template x-if="hasBank && hasPaypal">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    You’ve reached your payout method limit (1 bank + 1 PayPal). Edit an existing method above.
                </div>
            </template>

            {{-- Form only when there is something left to add --}}
            <template x-if="!(hasBank && hasPaypal)">
                <form method="POST" action="{{ route('profile.payouts.store') }}" class="space-y-4">
                    @csrf

                    {{-- Type selector (Bank disabled once one exists; PayPal disabled once saved) --}}
                    <div class="flex gap-6">
                        <label class="inline-flex items-center gap-2"
                               :class="hasBank ? 'opacity-50 cursor-not-allowed' : ''"
                               :title="hasBank ? 'You already saved a bank payout. Edit it above.' : ''">
                            <input type="radio" name="type" value="bank" x-model="type"
                                   class="text-indigo-600 border-gray-300"
                                   :disabled="hasBank">
                            <span>Bank transfer</span>
                        </label>

                        <label class="inline-flex items-center gap-2"
                               :class="hasPaypal ? 'opacity-50 cursor-not-allowed' : ''"
                               :title="hasPaypal ? 'You already saved a PayPal payout. Edit it above.' : ''">
                            <input type="radio" name="type" value="paypal" x-model="type"
                                   class="text-indigo-600 border-gray-300"
                                   :disabled="hasPaypal">
                            <span>PayPal</span>
                        </label>
                    </div>

                    {{-- BANK FORM (only when selected and user still allowed to add one) --}}
                    <div x-show="type === 'bank' && !hasBank" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Country"/>
                            <select name="country" x-model="country" class="w-full rounded border-gray-300">
                                @foreach ([
                                    'GB'=>'United Kingdom','US'=>'United States','CA'=>'Canada',
                                    'AU'=>'Australia','IN'=>'India','NG'=>'Nigeria','KE'=>'Kenya','GH'=>'Ghana'
                                ] as $cc => $label)
                                    <option value="{{ $cc }}">{{ $label }} ({{ $cc }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label value="Account holder name"/>
                            <x-text-input name="account_name" class="block w-full mt-1" />
                            <x-input-error :messages="$errors->get('account_name')" class="mt-2" />
                        </div>

                        {{-- GB --}}
                        <template x-if="country==='GB'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Sort code (6 digits)"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number (8 digits)"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- US --}}
                        <template x-if="country==='US'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Routing number (9 digits)"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- AU --}}
                        <template x-if="country==='AU'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="BSB (6 digits)"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- CA --}}
                        <template x-if="country==='CA'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Institution / transit (use your bank format)"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- IN --}}
                        <template x-if="country==='IN'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="IFSC code"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- NG --}}
                        <template x-if="country==='NG'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Bank code"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number (10 digits)"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- KE --}}
                        <template x-if="country==='KE'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Bank name/code"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        {{-- GH --}}
                        <template x-if="country==='GH'">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Bank name/code"/>
                                    <x-text-input name="sort_code" class="block w-full mt-1"/>
                                </div>
                                <div>
                                    <x-input-label value="Account number"/>
                                    <x-text-input name="account_number" class="block w-full mt-1"/>
                                </div>
                            </div>
                        </template>

                        <div class="sm:col-span-2">
                            <x-input-label value="IBAN / SWIFT (optional)"/>
                            <x-text-input name="iban" class="block w-full mt-1"/>
                        </div>
                    </div>

                    {{-- PAYPAL FORM (only when selectable) --}}
                    <template x-if="type === 'paypal' && !hasPaypal">
                        <div>
                            <input type="hidden" name="country" value="ZZ">
                            <x-input-label value="PayPal email"/>
                            <x-text-input type="email" name="paypal_email" class="block w-full mt-1" />
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-2" />
                        </div>
                    </template>

                    <button type="submit"
                            class="mt-2 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        Save payout method
                    </button>
                </form>
            </template>
        </div>
    </div>
</x-app-layout>
