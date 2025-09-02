<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Edit payout method</h2></x-slot>

    <div class="max-w-xl mx-auto p-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <form method="POST" action="{{ route('profile.payouts.update', $method) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @if ($method->type === 'paypal')
                    <div>
                        <x-input-label value="PayPal email"/>
                        <x-text-input type="email" name="paypal_email" class="block w-full mt-1"
                                      value="{{ old('paypal_email', $method->paypal_email) }}" required/>
                        @error('paypal_email') <x-input-error :messages="$errors->get('paypal_email')" class="mt-2" /> @enderror
                    </div>
                @else
                    <div>
                        <x-input-label value="Country"/>
                        <x-text-input class="block w-full mt-1 bg-gray-100" value="{{ $countryName }} ({{ $method->country }})" disabled/>
                    </div>

                    <div>
                        <x-input-label value="Account holder name"/>
                        <x-text-input name="account_name" class="block w-full mt-1"
                                      value="{{ old('account_name', $method->account_name) }}" required/>
                        @error('account_name') <x-input-error :messages="$errors->get('account_name')" class="mt-2" /> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Bank / routing / sort code"/>
                            <x-text-input name="sort_code" class="block w-full mt-1"
                                          value="{{ old('sort_code', $method->sort_code) }}" required/>
                        </div>
                        <div>
                            <x-input-label value="Account number"/>
                            <x-text-input name="account_number" class="block w-full mt-1"
                                          value="{{ old('account_number', $method->account_number) }}" required/>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="IBAN (optional)"/>
                            <x-text-input name="iban" class="block w-full mt-1"
                                          value="{{ old('iban', $method->iban) }}"/>
                        </div>
                        <div>
                            <x-input-label value="SWIFT (optional)"/>
                            <x-text-input name="swift" class="block w-full mt-1"
                                          value="{{ old('swift', $method->swift) }}"/>
                        </div>
                    </div>
                @endif

                <x-primary-button>Save changes</x-primary-button>
                <a href="{{ route('profile.payouts') }}" class="ml-3 text-gray-600 hover:text-gray-800 underline">Cancel</a>
            </form>
        </div>
    </div>
</x-app-layout>
