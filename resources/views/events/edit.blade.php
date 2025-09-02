<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Event — {{ $event->name }}
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back to dashboard</a>
        </div>
    </x-slot>

    @php
        // Normalize tags to array for the multiselect
        $raw = $event->tags;
        if (is_array($raw)) {
            $tags = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $tags = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : array_filter(array_map('trim', preg_split('/[,;]+/', $raw)));
        } else {
            $tags = [];
        }

        $cats = [
            'Arts','Business','Charity','Community','Education','Entertainment','Food & Drink',
            'Fashion','Health','Music','Religion','Sports','Technology','Travel'
        ];

        // Compact list of the current user’s payout methods using ONLY columns your table has
        $rawMethods = auth()->user()
            ? auth()->user()->payoutMethods()
                ->select(['id','type','country','paypal_email','account_name','account_number'])
                ->get()
                ->map(function ($m) {
                    $isBank = $m->type === 'bank';
                    $last4  = $isBank ? substr(preg_replace('/\D+/', '', (string) $m->account_number), -4) : null;

                    return [
                        'id'      => $m->id,
                        'type'    => $m->type,                       // 'bank' | 'paypal'
                        'country' => strtoupper($m->country ?? ''),  // 'GB', 'US', ...
                        'label'   => $isBank ? ($m->account_name ?: 'Bank account') : ($m->paypal_email ?: 'PayPal'),
                        'last4'   => $last4,
                        'email'   => $m->paypal_email,
                    ];
                })
                ->values()
            : collect();

        $initialCost     = old('ticket_cost', $event->ticket_cost); // keep numeric value
        $initialCurrency = old('ticket_currency', $event->ticket_currency ?: 'GBP');
        $initialPaid     = (float)$initialCost > 0;
    @endphp

    <div
        x-data="editEvent({
            methods: @js($rawMethods),
            defaultCurrency: '{{ $initialCurrency }}',
            defaultPaid: {{ $initialPaid ? 'true' : 'false' }},
            defaultCost: '{{ $initialCost !== null ? $initialCost : '' }}',
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

        {{-- Progress --}}
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">1) Pricing & payout</div>
            <div :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">2) Basics</div>
            <div :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                 class="rounded-xl px-4 py-3 text-center text-sm font-medium">3) Schedule & media</div>
        </div>

        <form action="{{ route('events.update', $event) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- STEP 1 — Pricing & Payout --}}
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
                                   x-model="ticketCost"
                                   :readonly="pricing==='free'"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-500 mt-1" x-show="pricing==='free'">Read-only for free events (set to 0.00).</p>
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

                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <h3 class="text-lg font-semibold text-gray-900">Payout destination</h3>

                    {{-- Found methods for that country (+ PayPal) --}}
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

                    {{-- None saved --}}
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
                                   value="{{ old('name', $event->name) }}"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organizer</label>
                            <input type="text" name="organizer" placeholder="Organization or person"
                                   value="{{ old('organizer', $event->organizer) }}"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <option value="">— Select —</option>
                                @foreach ($cats as $cat)
                                    <option value="{{ $cat }}" @selected(old('category', $event->category) === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                            <select id="tags" name="tags[]" multiple class="w-full rounded-lg border-gray-300">
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag }}" selected>{{ $tag }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Type and press enter to add.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input id="location-input" type="text" name="location"
                                   value="{{ old('location', $event->location) }}"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Venue, address or place name" autocomplete="off">
                            <input type="hidden" name="location_place_id" id="location_place_id" value="{{ old('location_place_id', $event->location_place_id ?? '') }}">
                            <input type="hidden" name="location_lat" id="location_lat" value="{{ old('location_lat', $event->location_lat ?? '') }}">
                            <input type="hidden" name="location_lng" id="location_lng" value="{{ old('location_lng', $event->location_lng ?? '') }}">
                            <p class="text-xs text-gray-500 mt-1">Start typing and choose a suggestion.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="4"
                                      class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">{{ old('description', $event->description) }}</textarea>
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
                        @php $existing = $event->sessions()->orderBy('session_date')->get(); @endphp

                        @forelse ($existing as $i => $s)
                            @php $date = \Carbon\Carbon::parse($s->session_date); @endphp
                            <div class="session-item rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-gray-700">Session {{ $i+1 }}</h4>
                                    <button type="button" class="remove-session text-sm text-rose-600 hover:text-rose-700">Remove</button>
                                </div>

                                <input type="hidden" name="sessions[{{ $i }}][id]" value="{{ $s->id }}">
                                <input type="hidden" name="sessions[{{ $i }}][_delete]" value="0">

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Title</label>
                                        <input type="text" name="sessions[{{ $i }}][name]"
                                               value="{{ old("sessions.$i.name", $s->session_name) }}"
                                               class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Date</label>
                                        <input type="date" name="sessions[{{ $i }}][date]"
                                               value="{{ old("sessions.$i.date", $date->format('Y-m-d')) }}"
                                               class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                        <input type="time" name="sessions[{{ $i }}][time]"
                                               value="{{ old("sessions.$i.time", $date->format('H:i')) }}"
                                               class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="session-item rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-gray-700">Session 1</h4>
                                    <button type="button" class="remove-session text-sm text-rose-600 hover:text-rose-700 hidden">Remove</button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Title</label>
                                        <input type="text" name="sessions[0][name]" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Date</label>
                                        <input type="date" name="sessions[0][date]" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                        <input type="time" name="sessions[0][time]" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                </div>
                            </div>
                        @endforelse
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event banner (replace)</label>
                            <input type="file" name="banner" accept="image/*" class="w-full rounded-lg border-gray-300">
                            @if ($event->banner_url)
                                <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                                    <img src="{{ asset('storage/'.$event->banner_url) }}" alt="banner" class="h-12 w-20 rounded object-cover">
                                    <span class="underline truncate">{{ $event->banner_url }}</span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event avatar (replace)</label>
                            <input type="file" name="avatar" accept="image/*" class="w-full rounded-lg border-gray-300">
                            @if ($event->avatar_url)
                                <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                                    <img src="{{ asset('storage/'.$event->avatar_url) }}" alt="avatar" class="h-10 w-10 rounded object-cover">
                                    <span class="underline truncate">{{ $event->avatar_url }}</span>
                                </div>
                            @endif
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
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700">
                        Save Changes
                    </button>
                </div>
            </section>
        </form>
    </div>

    {{-- Tom Select --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        // Alpine state for the edit flow
        document.addEventListener('alpine:init', () => {
            Alpine.data('editEvent', (cfg) => ({
                step: 1,

                currencies: ['GBP','USD','CAD','AUD','INR','NGN','KES','GHS'],
                pricing: cfg.defaultPaid ? 'paid' : 'free',
                currency: (cfg.defaultCurrency || 'GBP').toUpperCase(),
                ticketCost: cfg.defaultPaid
                    ? (cfg.defaultCost || '0.00')
                    : '0.00',

                country: '',
                allMethods: cfg.methods || [],
                eligibleMethods: [],
                chosenMethodId: null,
                profilePayoutUrl: cfg.profilePayoutUrl,

                init() {
                    if (this.pricing === 'free') this.ticketCost = '0.00';
                    this.updateCountry();
                    this.refreshEligible();

                    this.$watch('currency', () => { this.updateCountry(); this.refreshEligible(); });
                    this.$watch('pricing',  () => { if (this.pricing==='free') this.ticketCost = '0.00'; this.refreshEligible(); });
                },

                mapCurrencyToCountry(c) {
                    c = String(c || '').toUpperCase();
                    const map = { GBP:'GB', USD:'US', CAD:'CA', AUD:'AU', INR:'IN', NGN:'NG', KES:'KE', GHS:'GH' };
                    return map[c] || 'GB';
                },

                updateCountry() { this.country = this.mapCurrencyToCountry(this.currency); },

                refreshEligible() {
                    if (this.pricing !== 'paid') { this.eligibleMethods = []; this.chosenMethodId = null; return; }
                    const banks  = this.allMethods.filter(m => m.type === 'bank' && (m.country || '').toUpperCase() === this.country);
                    const paypal = this.allMethods.find(m => m.type === 'paypal');
                    this.eligibleMethods = paypal ? [...banks, paypal] : banks;
                    this.chosenMethodId  = this.eligibleMethods.length ? this.eligibleMethods[0].id : null;
                },

                methodTitle(m)  { return m.type === 'bank' ? `Bank — ${m.country}` : 'PayPal'; },
                methodSubtitle(m){ return m.type === 'bank' ? (m.last4 ? `${m.label} — ****${m.last4}` : m.label) : (m.email || m.label); },

                goStep(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }); }
            }));
        });

        // Tags
        document.addEventListener("DOMContentLoaded", function () {
            new TomSelect("#tags", {
                plugins: ['remove_button'],
                persist: false,
                create: true,
                createOnBlur: true,
                placeholder: "Add tags…",
                delimiter: ',',
            });
        });

        // Sessions UI (mark-for-delete supported)
        (function () {
            const wrapper = document.getElementById('sessions-wrapper');
            const addBtn  = document.getElementById('add-session');
            let sessionIndex = {{ max(($existing->count() ?? 0), 1) }};

            function renumber() {
                const items = wrapper.querySelectorAll('.session-item');
                items.forEach((el, i) => {
                    el.querySelector('h4').textContent = `Session ${i + 1}`;
                    const btn = el.querySelector('.remove-session');
                    btn.classList.toggle('hidden', items.length === 1);
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
                            <input type="text" name="sessions[${sessionIndex}][name]" class="w-full rounded-lg border-gray-300" required placeholder="e.g., Workshop">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Date</label>
                            <input type="date" name="sessions[${sessionIndex}][date]" class="w-full rounded-lg border-gray-300" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Start time</label>
                            <input type="time" name="sessions[${sessionIndex}][time]" class="w-full rounded-lg border-gray-300" required>
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
                const del = item.querySelector('input[name$="[_delete]"]');
                if (del) { del.value = '1'; item.style.display = 'none'; } else { item.remove(); }
                renumber();
            });

            renumber();
        })();

        // Google Places
        window.initPlaces = function () {
            const input = document.getElementById('location-input');
            if (!input || !window.google || !google.maps || !google.maps.places) return;

            const ac = new google.maps.places.Autocomplete(input, {
                fields: ['place_id','geometry','formatted_address','name'],
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

    @if (config('services.google.maps_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initPlaces" async defer></script>
    @endif
</x-app-layout>
