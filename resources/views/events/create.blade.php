{{-- resources/views/events/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Create Event
        </h2>
    </x-slot>

    @php
        // Build a compact list of the user’s payout methods using ONLY columns your table has
        $rawMethods = auth()->user()
            ? auth()->user()->payoutMethods()
                ->select(['id','type','country','paypal_email','account_name','account_number'])
                ->get()
                ->map(function ($m) {
                    $isBank = $m->type === 'bank';
                    $last4  = $isBank ? substr(preg_replace('/\D+/', '', (string) $m->account_number), -4) : null;

                    return [
                        'id'           => $m->id,
                        'type'         => $m->type,                   // 'bank' | 'paypal'
                        'country'      => strtoupper($m->country ?? ''), // 'GB', 'US', ...
                        'label'        => $isBank
                                            ? ($m->account_name ?: 'Bank account')
                                            : ($m->paypal_email ?: 'PayPal'),
                        'last4'        => $last4,
                        'email'        => $m->paypal_email,
                    ];
                })
                ->values()
            : collect();
    @endphp

    <div
        x-data="createEvent({
            methods: @js($rawMethods),
            defaultCurrency: '{{ old('ticket_currency','GBP') }}',
            defaultPaid: {{ old('ticket_cost', '') !== '' && (float)old('ticket_cost') > 0 ? 'true' : 'false' }},
            profilePayoutUrl: '{{ route('profile.payouts') }}'
        })"
        class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        {{-- errors --}}
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

        {{-- progress --}}
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">1) Pricing & payout</div>
            <div :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">2) Basics</div>
            <div :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">3) Schedule & media</div>
        </div>

        <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" @submit.prevent="validateAndSubmit">
            @csrf

            {{-- STEP 1 — Pricing & Payouts --}}
            <section x-show="step === 1" x-cloak class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Pricing</h3>

                    <div class="mt-4 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" class="text-indigo-600 border-gray-300"
                                   value="free" x-model="pricing">
                            <span>Free event</span>
                        </label>

                        <label class="inline-flex items-center gap-2">
                            <input type="radio" class="text-indigo-600 border-gray-300"
                                   value="paid" x-model="pricing">
                            <span>Paid event</span>
                        </label>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ticket cost</label>
                            <input type="number" step="0.01" min="0"
                                   name="ticket_cost"
                                   x-bind:disabled="pricing==='free'"
                                   x-bind:required="pricing==='paid'"
                                   x-model="ticketCost"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-500 mt-1" x-show="pricing==='free'">Disabled for free events.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <select name="ticket_currency"
                                    x-model="currency"
                                    class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <template x-for="c in currencies" :key="c">
                                    <option :value="c" x-text="c"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">We’ll map currency to payout country automatically.</p>
                        </div>
                    </div>
                </div>

                {{-- Payout destination (only for paid events) --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <h3 class="text-lg font-semibold text-gray-900">Payout destination</h3>

                    {{-- selected / found --}}
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

                    {{-- none saved --}}
                    <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 text-sm"
                         x-show="pricing==='paid' && !eligibleMethods.length">
                        No payout method saved for <span class="font-semibold" x-text="country"></span>.
                        <a class="underline" :href="profilePayoutUrl + '?country=' + country" target="_blank">Add one now</a>,
                        then come back and refresh.
                    </div>
                </div>

                 {{-- FOOTER (Continue button is disabled until a payout is chosen for paid events) --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-amber-700"
                          x-show="pricing==='paid' && !chosenMethodId">
                        Select a payout destination to continue.
                    </span>

                    <button type="button"
                            @click="goStep(2)"
                            :disabled="pricing==='paid' && !chosenMethodId"
                            aria-disabled="true"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700
                                   disabled:opacity-50 disabled:cursor-not-allowed">
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
                            <input type="text" name="name" required
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500"
                                   placeholder="e.g., Product Launch 2025">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organizer</label>
                            <input type="text" name="organizer"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Organization or person">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <option value="">— Select —</option>
                                @foreach ([
                                    'Arts','Business','Charity','Community','Education','Entertainment','Food & Drink',
                                    'Fashion','Health','Music','Religion','Sports','Technology','Travel'
                                ] as $cat)
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
                            <input id="location-input" type="text" name="location"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Venue, address or place name" autocomplete="off">
                            <input type="hidden" name="location_place_id" id="location_place_id">
                            <input type="hidden" name="location_lat" id="location_lat">
                            <input type="hidden" name="location_lng" id="location_lng">
                            <p class="text-xs text-gray-500 mt-1">Start typing and choose a suggestion.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="4"
                                      class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500"
                                      placeholder="Tell people what to expect"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button"
                            @click="goStep(1)"
                            class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">
                        Back
                    </button>
                    <button type="button"
                            @click="goStep(3)"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700">
                        Continue
                    </button>
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
                                    <input type="text" name="sessions[0][name]" required
                                           placeholder="e.g., Opening Keynote"
                                           class="w-full rounded-lg border-gray-300">
                                    <p class="mt-1 text-xs text-gray-500">What you call this session.</p>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Date</label>
                                    <input type="date" name="sessions[0][date]" required
                                           class="w-full rounded-lg border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                    <input type="time" name="sessions[0][time]" required
                                           class="w-full rounded-lg border-gray-300">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-session"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        + Add another session
                    </button>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Media</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event banner (required)</label>
                            <p class="text-xs text-gray-500 mb-2">Recommended 1200×300 (4:1)</p>
                            <input type="file" name="banner" class="w-full rounded-lg border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event avatar (optional)</label>
                            <p class="text-xs text-gray-500 mb-2">Used for attendee display pictures</p>
                            <input type="file" name="avatar" class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button"
                            @click="goStep(2)"
                            class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">
                        Back
                    </button>

                    <button type="submit"
                            :disabled="pricing==='paid' && !chosenMethodId"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-white hover:bg-emerald-700 disabled:opacity-50">
                        Create Event
                    </button>
                </div>
            </section>
        </form>
    </div>

    {{-- Scripts --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('createEvent', (cfg) => ({
                step: 1,

                // Pricing
                pricing: cfg.defaultPaid ? 'paid' : 'free',
                ticketCost: cfg.defaultPaid ? '{{ old('ticket_cost') }}' : '',
                currencies: ['GBP','USD','CAD','AUD','INR','NGN','KES','GHS'],
                currency: (cfg.defaultCurrency || 'GBP').toUpperCase(),
                country: '',

                // Payouts
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
                    const map = { GBP:'GB', USD:'US', CAD:'CA', AUD:'AU', INR:'IN', NGN:'NG', KES:'KE', GHS:'GH' };
                    return map[c] || 'GB';
                },

                updateCountry() {
                    this.country = this.mapCurrencyToCountry(this.currency);
                },

                refreshEligible() {
                    if (this.pricing !== 'paid') { this.eligibleMethods = []; this.chosenMethodId = null; return; }

                    const banks  = this.allMethods.filter(m => m.type === 'bank' && (m.country || '').toUpperCase() === this.country);
                    const paypal = this.allMethods.find(m => m.type === 'paypal'); // one total

                    this.eligibleMethods = paypal ? [...banks, paypal] : banks;
                    this.chosenMethodId  = this.eligibleMethods.length ? this.eligibleMethods[0].id : null;
                },

                methodTitle(m) {
                    return m.type === 'bank' ? `Bank — ${m.country}` : 'PayPal';
                },
                methodSubtitle(m) {
                    if (m.type === 'bank') {
                        return m.last4 ? `${m.label} — ****${m.last4}` : m.label;
                    }
                    return m.email || m.label;
                },

                goStep(n) {
                    // require payout for paid events before leaving step 1
                    if (n > 1 && this.pricing === 'paid' && !this.chosenMethodId) {
                        alert('Please select or add a payout method for ' + this.country);
                        return;
                    }
                    this.step = n;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                validateAndSubmit(e) {
                    if (this.pricing === 'paid' && !this.chosenMethodId) {
                        this.step = 1;
                        alert('Please select or add a payout method before submitting.');
                        return;
                    }
                    e.target.submit();
                }
            }));
        });

        // Sessions UI
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
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Title</label>
                                <input type="text" name="sessions[${sessionIndex}][name]" required class="w-full rounded-lg border-gray-300" placeholder="e.g., Workshop">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Date</label>
                                <input type="date" name="sessions[${sessionIndex}][date]" required class="w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                <input type="time" name="sessions[${sessionIndex}][time]" required class="w-full rounded-lg border-gray-300">
                            </div>
                        </div>
                    </div>`;
                wrapper.insertAdjacentHTML('beforeend', html);
                sessionIndex++;
                renumber();
            });

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.remove-session');
                if (!btn) return;
                const item = btn.closest('.session-item');
                item.remove();
                renumber();
            });

            renumber();
        })();

        // Tags (Tom Select)
        document.addEventListener("DOMContentLoaded", function () {
            if (window.TomSelect) {
                new TomSelect("#tags", {
                    plugins: ['remove_button'],
                    persist: false,
                    create: true,
                    createOnBlur: true,
                    placeholder: "Add tags…",
                    delimiter: ','
                });
            }
        });

        // Google Places
        window.initPlaces = function () {
            const input = document.getElementById('location-input');
            if (!input || !window.google || !google.maps || !google.maps.places) return;

            const ac = new google.maps.places.Autocomplete(input, {
                fields: ['place_id', 'geometry', 'formatted_address', 'name'],
                types: ['geocode']
            });

            ac.addListener('place_changed', () => {
                const place = ac.getPlace();
                if (!place || !place.geometry) return;

                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                document.getElementById('location_place_id').value = place.place_id || '';
                document.getElementById('location_lat').value = lat;
                document.getElementById('location_lng').value = lng;

                if (place.formatted_address) input.value = place.formatted_address;
            });
        };
    </script>

    {{-- Load Google Maps Places only if a key is configured --}}
    @if (config('services.google.maps_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initPlaces" async defer></script>
    @else
        <div class="max-w-4xl mx-auto mt-4 text-yellow-700 bg-yellow-50 border border-yellow-200 p-3 rounded">
            Google Maps key is not configured. Add <code>GOOGLE_MAPS_API_KEY</code> to your .env file.
        </div>
    @endif
</x-app-layout>
