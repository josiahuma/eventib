{{-- resources/views/events/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Create Event
        </h2>
    </x-slot>

    @php
        $rawMethods = auth()->user()
            ? auth()->user()->payoutMethods()
                ->select(['id','type','country','paypal_email','account_name','account_number'])
                ->get()
                ->map(function ($m) {
                    $isBank = $m->type === 'bank';
                    $last4  = $isBank ? substr(preg_replace('/\D+/', '', (string) $m->account_number), -4) : null;
                    return [
                        'id'      => $m->id,
                        'type'    => $m->type,
                        'country' => strtoupper($m->country ?? ''),
                        'label'   => $isBank ? ($m->account_name ?: 'Bank account') : ($m->paypal_email ?: 'PayPal'),
                        'last4'   => $last4,
                        'email'   => $m->paypal_email,
                    ];
                })->values()
            : collect();
    @endphp

    <div
        x-data="createEvent({
            methods: @js($rawMethods),
            defaultCurrency: '{{ old('ticket_currency','GBP') }}',
            defaultPricing: '{{ old('pricing','free') }}',
            profilePayoutUrl: '{{ route('profile.payouts') }}'
        })"
        class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 p-4">
                <div class="font-semibold mb-1">Please fix the following:</div>
                <ul class="list-disc ms-5 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-6 grid grid-cols-3 gap-3">
            <div :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">1) Pricing & payout</div>
            <div :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">2) Basics</div>
            <div :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">3) Schedule & media</div>
        </div>

        {{-- NOTE: pass $event to the handler so we can submit safely --}}
        <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" @submit.prevent="validateAndSubmit($event)">
            @csrf

            {{-- always submit a currency (controller tolerates it for free) --}}
            <input type="hidden" name="ticket_currency" :value="currency">

            {{-- STEP 1 --}}
            <section x-show="step === 1" x-cloak class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Pricing</h3>

                    <div class="mt-4 flex flex-wrap gap-6">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" class="text-indigo-600 border-gray-300" value="free" x-model="pricing" name="pricing">
                            <span>Free event</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" class="text-indigo-600 border-gray-300" value="paid" x-model="pricing" name="pricing">
                            <span>Paid event</span>
                        </label>
                    </div>

                    {{-- Currency (paid only) --}}
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="pricing==='paid'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <select name="ticket_currency" x-model="currency" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <template x-for="c in currencies" :key="c">
                                    <option :value="c" x-text="c"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">We’ll map currency to payout country automatically.</p>
                        </div>
                    </div>
                </div>

                {{-- Ticket Types (paid only) --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Ticket types</h3>
                        <button type="button" id="add-cat" class="px-3 py-1.5 text-sm rounded-md border bg-white hover:bg-gray-50">Add</button>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        Create one or more tickets (e.g., Standard, VIP, Early Bird). At least one is required.
                    </p>

                    <div id="cat-rows" class="mt-4 space-y-3">
                        @php($cats = old('categories', []))
                        @forelse($cats as $i => $c)
                            <div class="cat-row rounded-lg border border-gray-200 p-3">
                                <div class="grid grid-cols-1 sm:grid-cols-12 sm:gap-3">
                                    <div class="sm:col-span-5">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Ticket name</label>
                                        <input
                                            name="categories[{{ $i }}][name]"
                                            value="{{ $c['name'] ?? '' }}"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="e.g., Standard"
                                            :required="pricing==='paid'"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-3 mt-3 sm:mt-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                        <input
                                            type="number" step="0.01" min="0"
                                            name="categories[{{ $i }}][price]"
                                            value="{{ $c['price'] ?? 0 }}"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="0.00"
                                            :required="pricing==='paid'"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-3 mt-3 sm:mt-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (optional)</label>
                                        <input
                                            type="number" min="0"
                                            name="categories[{{ $i }}][capacity]"
                                            value="{{ $c['capacity'] ?? '' }}"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="e.g., 100"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-1 mt-3 sm:mt-0 flex sm:items-end sm:justify-end">
                                        <button type="button" class="remove-cat text-rose-600 text-sm">Remove</button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            {{-- one empty row by default --}}
                            <div class="cat-row rounded-lg border border-gray-200 p-3">
                                <div class="grid grid-cols-1 sm:grid-cols-12 sm:gap-3">
                                    <div class="sm:col-span-5">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Ticket name</label>
                                        <input
                                            name="categories[0][name]"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="e.g., Standard"
                                            :required="pricing==='paid'"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-3 mt-3 sm:mt-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                        <input
                                            type="number" step="0.01" min="0"
                                            name="categories[0][price]"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="0.00"
                                            :required="pricing==='paid'"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-3 mt-3 sm:mt-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (optional)</label>
                                        <input
                                            type="number" min="0"
                                            name="categories[0][capacity]"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="e.g., 100"
                                            :disabled="pricing!=='paid'">
                                    </div>

                                    <div class="sm:col-span-1 mt-3 sm:mt-0 flex sm:items-end sm:justify-end">
                                        <button type="button" class="remove-cat text-rose-600 text-sm">Remove</button>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    {{-- template used by your existing JS add-row logic --}}
                    <template id="cat-tpl">
                        <div class="cat-row rounded-lg border border-gray-200 p-3">
                            <div class="grid grid-cols-1 sm:grid-cols-12 sm:gap-3">
                                <div class="sm:col-span-5">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ticket name</label>
                                    <input name="__IDX__[name]" class="w-full rounded-lg border-gray-300" placeholder="e.g., VIP" :required="pricing==='paid'" :disabled="pricing!=='paid'">
                                </div>
                                <div class="sm:col-span-3 mt-3 sm:mt-0">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                    <input type="number" step="0.01" min="0" name="__IDX__[price]" class="w-full rounded-lg border-gray-300" placeholder="0.00" :required="pricing==='paid'" :disabled="pricing!=='paid'">
                                </div>
                                <div class="sm:col-span-3 mt-3 sm:mt-0">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (optional)</label>
                                    <input type="number" min="0" name="__IDX__[capacity]" class="w-full rounded-lg border-gray-300" placeholder="e.g., 100" :disabled="pricing!=='paid'">
                                </div>
                                <div class="sm:col-span-1 mt-3 sm:mt-0 flex sm:items-end sm:justify-end">
                                    <button type="button" class="remove-cat text-rose-600 text-sm">Remove</button>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>

                {{-- Fee handling (paid only) --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <h3 class="text-lg font-semibold text-gray-900">Platform fee</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Payment processing fee is <b>5.9%</b> per transaction. You can choose to pass on the platform fee to your attendees, or absorb it yourself.
                        This fee helps us cover payment processing and development costs. This option CANNOT be modified after creating the event.
                    </p>

                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" class="mt-1 text-indigo-600 border-gray-300"
                                name="fee_mode" value="absorb"
                                @checked(old('fee_mode', 'absorb') === 'absorb')>
                            <div>
                                <div class="font-medium text-gray-900">Organiser absorbs fee</div>
                                <div class="text-sm text-gray-600">
                                    Attendees pay the ticket price. Your payout is ticket revenue minus 5.9%.
                                </div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" class="mt-1 text-indigo-600 border-gray-300"
                                name="fee_mode" value="pass"
                                @checked(old('fee_mode') === 'pass')>
                            <div>
                                <div class="font-medium text-gray-900">Pass fee to attendees</div>
                                <div class="text-sm text-gray-600">
                                    Attendees pay ticket price <i>plus</i> 5.9% at checkout. Your payout is the full ticket price.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>


                {{-- Payout destination (paid only) --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <h3 class="text-lg font-semibold text-gray-900">Payout destination</h3>
                    <template x-if="eligibleMethods.length">
                        <div class="mt-3 grid grid-cols-1 gap-3">
                            <template x-for="m in eligibleMethods" :key="m.id">
                                <label class="cursor-pointer rounded-xl border p-3 flex items-start gap-3"
                                       :class="chosenMethodId===m.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'">
                                    <input type="radio" class="mt-1 text-indigo-600 border-gray-300"
                                           name="payout_method_id" :value="m.id" x-model="chosenMethodId">
                                    <div>
                                        <div class="font-medium text-gray-900" x-text="methodTitle(m)"></div>
                                        <div class="text-sm text-gray-600" x-text="methodSubtitle(m)"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </template>

                    <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 text-sm"
                         x-show="pricing==='paid' && !eligibleMethods.length">
                        No payout method saved for <span class="font-semibold" x-text="country"></span>.
                        <a class="underline" :href="profilePayoutUrl + '?country=' + country" target="_blank">Add one now</a>, then refresh.
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm text-amber-700" x-show="pricing==='paid' && (!chosenMethodId)">Select a payout destination to continue.</span>
                    <button type="button" @click="goStep(2)" :disabled="pricing==='paid' && !chosenMethodId"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700 disabled:opacity-50">
                        Continue
                    </button>
                </div>
            </section>

            {{-- STEP 2 — Basics --}}
            <section x-show="step === 2" x-cloak class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Basics</h3>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event name</label>
                            <input type="text" name="name" required class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Product Launch 2025">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organizer</label>
                            <input type="text" name="organizer" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500" placeholder="Organization or person">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <option value="">— Select —</option>
                                @foreach (['Arts','Business','Charity','Community','Education','Entertainment','Food & Drink','Fashion','Health','Music','Religion','Sports','Technology','Travel'] as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                            <select id="tags" name="tags[]" multiple class="w-full rounded-lg border-gray-300"></select>
                            <p class="text-xs text-gray-500 mt-1">Type and press enter to add. You can add multiple.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input id="location-input" type="text" name="location" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500" placeholder="Venue, address or place name" autocomplete="off">
                            <input type="hidden" name="location_place_id" id="location_place_id">
                            <input type="hidden" name="location_lat" id="location_lat">
                            <input type="hidden" name="location_lng" id="location_lng">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="4" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500" placeholder="Tell people what to expect"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button" @click="goStep(1)" class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">Back</button>
                    <button type="button" @click="goStep(3)" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700">Continue</button>
                </div>
            </section>

            {{-- STEP 3 — Schedule & Media --}}
            <section x-show="step === 3" x-cloak class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Schedule</h3>

                    <div id="sessions-wrapper" class="mt-4 space-y-4">
                        <div class="session-item rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-700">Session 1</h4>
                                <button type="button" class="remove-session text-sm text-rose-600 hover:text-rose-700 hidden">Remove</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Title</label>
                                    <input type="text" name="sessions[0][name]" required class="w-full rounded-lg border-gray-300" placeholder="e.g., Opening Keynote">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Date</label>
                                    <input type="date" name="sessions[0][date]" required class="w-full rounded-lg border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                    <input type="time" name="sessions[0][time]" required class="w-full rounded-lg border-gray-300">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-session" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        + Add another session
                    </button>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Media</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event banner (required)</label>
                            <p class="text-xs text-gray-500 mb-2">Recommended 1200×300 (4:1)</p>
                            <input type="file" name="banner" class="w-full rounded-lg border-gray-300" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event avatar (optional)</label>
                            <input type="file" name="avatar" class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button" @click="goStep(2)" class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">Back</button>
                    <button type="submit" :disabled="pricing==='paid' && !chosenMethodId" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-white hover:bg-emerald-700 disabled:opacity-50">
                        Create Event
                    </button>
                </div>
            </section>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('createEvent', (cfg) => ({
                step: 1,
                pricing: cfg.defaultPricing === 'paid' ? 'paid' : 'free',
                currencies: ['GBP','USD','CAD','AUD','INR','NGN','KES','GHS','EUR'],
                currency: (cfg.defaultCurrency || 'GBP').toUpperCase(),
                country: '',
                allMethods: cfg.methods || [],
                eligibleMethods: [],
                chosenMethodId: null,
                profilePayoutUrl: cfg.profilePayoutUrl,

                init() {
                    this.updateCountry();
                    this.refreshEligible();
                    this.$watch('currency', () => { this.updateCountry(); this.refreshEligible(); });
                    this.$watch('pricing',  () => { this.refreshEligible(); });
                },

                mapCurrencyToCountry(c) {
                    c = String(c || '').toUpperCase();
                    const map = { GBP:'GB', USD:'US', CAD:'CA', AUD:'AU', INR:'IN', NGN:'NG', KES:'KE', GHS:'GH', EUR:'EU' };
                    return map[c] || 'GB';
                },
                updateCountry(){ this.country = this.mapCurrencyToCountry(this.currency); },
                refreshEligible(){
                    if (this.pricing !== 'paid') { this.eligibleMethods = []; this.chosenMethodId = null; return; }
                    const banks  = this.allMethods.filter(m => m.type === 'bank' && (m.country || '').toUpperCase() === this.country);
                    const paypal = this.allMethods.find(m => m.type === 'paypal');
                    this.eligibleMethods = paypal ? [...banks, paypal] : banks;
                    this.chosenMethodId  = this.eligibleMethods.length ? this.eligibleMethods[0].id : null;
                },

                methodTitle(m){ return m.type === 'bank' ? `Bank — ${m.country}` : 'PayPal'; },
                methodSubtitle(m){ return m.type === 'bank' ? (m.last4 ? `${m.label} — ****${m.last4}` : m.label) : (m.email || m.label); },

                goStep(n){
                    if (n > 1 && this.pricing==='paid' && !this.chosenMethodId) { alert('Select a payout destination for ' + this.country); return; }
                    this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                validateAndSubmit(e){
                    if (this.pricing==='paid') {
                        // ensure at least one ticket row with name + price
                        const rows = Array.from(document.querySelectorAll('#cat-rows .cat-row'));
                        const valid = rows.some(r => {
                            const name  = r.querySelector('input[name$="[name]"]')?.value?.trim();
                            const price = parseFloat(r.querySelector('input[name$="[price]"]')?.value || '0');
                            return name && price > 0;
                        });
                        if (!valid) { alert('Add at least one ticket type with a positive price.'); return; }
                        if (!this.chosenMethodId) { alert('Please choose a payout destination.'); return; }
                    }
                    (e?.target || this.$el.querySelector('form'))?.submit();
                }
            }));
        });

        // Sessions add/remove
        (function () {
            let sessionIndex = 1;
            const wrapper = document.getElementById('sessions-wrapper');
            const addBtn  = document.getElementById('add-session');

            function renumber() {
                const items = wrapper.querySelectorAll('.session-item');
                items.forEach((el, i) => {
                    el.querySelector('h4').textContent = `Session ${i + 1}`;
                    el.querySelector('.remove-session').classList.toggle('hidden', items.length === 1);
                });
            }

            addBtn.addEventListener('click', () => {
                const html = `
                    <div class="session-item rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-700">Session</h4>
                            <button type="button" class="remove-session text-sm text-rose-600 hover:text-rose-700">Remove</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><label class="block text-sm text-gray-700 mb-1">Title</label><input type="text" name="sessions[${sessionIndex}][name]" required class="w-full rounded-lg border-gray-300" placeholder="e.g., Workshop"></div>
                            <div><label class="block text-sm text-gray-700 mb-1">Date</label><input type="date" name="sessions[${sessionIndex}][date]" required class="w-full rounded-lg border-gray-300"></div>
                            <div><label class="block text-sm text-gray-700 mb-1">Start time</label><input type="time" name="sessions[${sessionIndex}][time]" required class="w-full rounded-lg border-gray-300"></div>
                        </div>
                    </div>`;
                wrapper.insertAdjacentHTML('beforeend', html);
                sessionIndex++; renumber();
            });

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.remove-session');
                if (!btn) return; btn.closest('.session-item').remove(); renumber();
            });

            renumber();
        })();

        // Ticket types UI add/remove
        (function () {
            const wrap = document.getElementById('cat-rows');
            const tpl  = document.getElementById('cat-tpl')?.innerHTML || '';
            const add  = document.getElementById('add-cat');

            // Find next numeric index by scanning existing inputs
            function nextIndex() {
                let max = -1;
                wrap.querySelectorAll('input[name^="categories["]').forEach(inp => {
                    const m = inp.name.match(/^categories\[(\d+)\]/);
                    if (m) max = Math.max(max, parseInt(m[1], 10));
                });
                return max + 1;
            }

            function wireRemove() {
                wrap.querySelectorAll('.remove-cat').forEach(btn => {
                    btn.onclick = () => btn.closest('.cat-row')?.remove();
                });
            }

            add?.addEventListener('click', () => {
                const idx = nextIndex();
                // Replace the token with a real categories[...] prefix
                const html = tpl.replaceAll('__IDX__', `categories[${idx}]`);
                const div = document.createElement('div');
                div.innerHTML = html.trim();
                wrap.appendChild(div.firstElementChild);
                wireRemove();
            });

            wireRemove();
        })();

        // Tom Select (tags)
        document.addEventListener("DOMContentLoaded", function () {
            if (window.TomSelect) {
                new TomSelect("#tags", { plugins: ['remove_button'], persist: false, create: true, createOnBlur: true, placeholder: "Add tags…", delimiter: ',' });
            }
        });

        // Google Places (optional)
        window.initPlaces = function () {
            const input = document.getElementById('location-input');
            if (!input || !window.google || !google.maps || !google.maps.places) return;
            const ac = new google.maps.places.Autocomplete(input, { fields: ['place_id','geometry','formatted_address','name'], types: ['geocode'] });
            ac.addListener('place_changed', () => {
                const p = ac.getPlace(); if (!p || !p.geometry) return;
                document.getElementById('location_place_id').value = p.place_id || '';
                document.getElementById('location_lat').value = p.geometry.location.lat();
                document.getElementById('location_lng').value = p.geometry.location.lng();
                if (p.formatted_address) input.value = p.formatted_address;
            });
        };
    </script>

    @if (config('services.google.maps_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initPlaces" async defer></script>
    @endif
</x-app-layout>
