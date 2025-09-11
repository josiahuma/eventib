{{-- resources/views/events/edit.blade.php --}}
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
        // Tags -> array (for multiselect)
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

        // Static categories for select
        $cats = [
            'Arts','Business','Charity','Community','Education','Entertainment',
            'Food & Drink','Fashion','Health','Music','Religion','Sports','Technology','Travel'
        ];

        // Payout methods (compact)
        $rawMethods = auth()->user()
            ? auth()->user()->payoutMethods()
                ->select(['id','type','country','paypal_email','account_name','account_number'])
                ->get()
                ->map(function ($m) {
                    $isBank = $m->type === 'bank';
                    $last4  = $isBank ? substr(preg_replace('/\D+/', '', (string) $m->account_number), -4) : null;
                    return [
                        'id'      => $m->id,
                        'type'    => $m->type,                        // 'bank' | 'paypal'
                        'country' => strtoupper($m->country ?? ''),   // ISO2
                        'label'   => $isBank ? ($m->account_name ?: 'Bank account') : ($m->paypal_email ?: 'PayPal'),
                        'last4'   => $last4,
                        'email'   => $m->paypal_email,
                    ];
                })
                ->values()
            : collect();

        // Ticket categories (existing)
        $catsExisting = method_exists($event, 'categories')
            ? $event->categories()->orderBy('sort')->orderBy('id')->get()
            : collect();

        // "Paid" if any ACTIVE category has price > 0 (fallback to single cost only for ancient events)
        $hasPaidCats     = $catsExisting->where('is_active', true)->filter(fn ($c) => (float)$c->price > 0)->isNotEmpty();
        $initialPaid     = $hasPaidCats || ((float)($event->ticket_cost ?? 0) > 0);
        $initialCurrency = old('ticket_currency', $event->ticket_currency ?: 'GBP');

        // Sessions for edit
        $existingSessions = $event->sessions()->orderBy('session_date')->get();
        $existingCount    = $existingSessions->count();
    @endphp

    <div
        x-data="editEvent({
            methods: @js($rawMethods),
            defaultCurrency: '{{ $initialCurrency }}',
            defaultPaid: {{ $initialPaid ? 'true' : 'false' }},
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
                            <select name="ticket_currency" x-model="currency" :required="pricing==='paid'"
                                    class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                                <template x-for="c in currencies" :key="c"><option :value="c" x-text="c"></option></template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">We’ll map currency to payout country automatically.</p>
                        </div>
                    </div>
                </div>

                {{-- Ticket types (paid only) --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Ticket types</h3>
                        <button type="button" id="add-cat" class="px-3 py-1.5 text-sm rounded-md border bg-white hover:bg-gray-50">Add</button>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        Create one or more tickets (e.g., Standard, VIP, Early Bird). At least one is recommended for paid events.
                    </p>

                    <div id="cat-rows" class="mt-4 space-y-2">
                        @foreach($catsExisting as $i => $c)
                            <div class="grid grid-cols-12 gap-2 items-center cat-row border p-2 rounded-lg">
                                <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $c->id }}">
                                <div class="col-span-5">
                                    <input name="categories[{{ $i }}][name]" value="{{ $c->name }}" class="w-full rounded-lg border-gray-300" placeholder="Name (e.g., Standard)">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" min="0" name="categories[{{ $i }}][price]" value="{{ $c->price }}" class="w-full rounded-lg border-gray-300" placeholder="Price">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" min="0" name="categories[{{ $i }}][capacity]" value="{{ $c->capacity }}" class="w-full rounded-lg border-gray-300" placeholder="Capacity (opt)">
                                </div>
                                <div class="col-span-2 flex items-center gap-2">
                                    <input type="number" min="0" name="categories[{{ $i }}][sort]" value="{{ $c->sort }}" class="w-full rounded-lg border-gray-300" placeholder="Sort">
                                    <label class="inline-flex items-center ms-2 text-sm">
                                        <input type="checkbox" name="categories[{{ $i }}][is_active]" value="1" @checked($c->is_active) class="rounded border-gray-300">
                                        <span class="ms-1">Active</span>
                                    </label>
                                </div>
                                <div class="col-span-1 text-right">
                                    <button type="button" class="remove-cat text-rose-600 text-sm">Remove</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <template id="cat-tpl">
                        <div class="grid grid-cols-12 gap-2 items-center cat-row border p-2 rounded-lg">
                            <input type="hidden" name="__IDX__[id]" value="">
                            <div class="col-span-5"><input name="__IDX__[name]" class="w-full rounded-lg border-gray-300" placeholder="Name (e.g., VIP)"></div>
                            <div class="col-span-2"><input type="number" step="0.01" min="0" name="__IDX__[price]" class="w-full rounded-lg border-gray-300" placeholder="Price"></div>
                            <div class="col-span-2"><input type="number" min="0" name="__IDX__[capacity]" class="w-full rounded-lg border-gray-300" placeholder="Capacity (opt)"></div>
                            <div class="col-span-2 flex items-center gap-2">
                                <input type="number" min="0" name="__IDX__[sort]" value="0" class="w-full rounded-lg border-gray-300" placeholder="Sort">
                                <label class="inline-flex items-center ms-2 text-sm">
                                    <input type="checkbox" name="__IDX__[is_active]" value="1" checked class="rounded border-gray-300"><span class="ms-1">Active</span>
                                </label>
                            </div>
                            <div class="col-span-1 text-right"><button type="button" class="remove-cat text-rose-600 text-sm">Remove</button></div>
                        </div>
                    </template>
                </div>

                {{-- Fee handling (paid only, READ-ONLY on edit) --}}
                @php
                    // whatever the event currently has; default to 'absorb' if missing
                    $feeMode = old('fee_mode', $event->fee_mode ?? 'absorb');
                @endphp

                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm" x-show="pricing==='paid'">
                    <div class="flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Platform fee</h3>
                        <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700"
                            title="This setting is locked after the event is created.">
                            {{-- lock icon --}}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2a5 5 0 00-5 5v3H6a2 2 0 00-2 2v7a2 2 0 002 2h12a2 2 0 002-2v-7a2 2 0 00-2-2h-1V7a5 5 0 00-5-5zm-3 8V7a3 3 0 116 0v3H9z"/>
                            </svg>
                            Locked
                        </span>
                    </div>

                    <p class="text-sm text-gray-600 mt-1">
                        Payment processing fee is <b>5.9%</b> per transaction. This choice was made when the event was created and
                        can’t be changed here.
                    </p>

                    {{-- Keep submitting the saved value, even though radios are disabled --}}
                    <input type="hidden" name="fee_mode" value="{{ $feeMode }}">

                    {{-- Make the radios visibly disabled and non-interactive --}}
                    <div class="mt-4 space-y-3 opacity-60 pointer-events-none select-none">
                        <label class="flex items-start gap-3">
                            <input type="radio" class="mt-1 text-indigo-600 border-gray-300"
                                name="fee_mode_view" value="absorb" disabled
                                @checked($feeMode === 'absorb')>
                            <div>
                                <div class="font-medium text-gray-900">Organiser absorbs fee</div>
                                <div class="text-sm text-gray-600">
                                    Attendees pay the ticket price. Your payout is ticket revenue minus 5.9%.
                                </div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3">
                            <input type="radio" class="mt-1 text-indigo-600 border-gray-300"
                                name="fee_mode_view" value="pass" disabled
                                @checked($feeMode === 'pass')>
                            <div>
                                <div class="font-medium text-gray-900">Pass fee to attendees</div>
                                <div class="text-sm text-gray-600">
                                    Attendees pay ticket price <i>plus</i> 5.9% at checkout. Your payout is the full ticket price.
                                </div>
                            </div>
                        </label>
                    </div>

                    {{-- Optional: clarify the active mode --}}
                    <div class="mt-3 text-xs text-gray-500">
                        Current setting: <span class="font-medium text-gray-800">
                            {{ $feeMode === 'pass' ? 'Pass fee to attendees' : 'Organiser absorbs fee' }}
                        </span>
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
                    <span class="text-sm text-amber-700" x-show="pricing==='paid' && !chosenMethodId">
                        Select a payout destination to continue.
                    </span>

                    <button type="button"
                            @click="goStep(2)"
                            :disabled="pricing==='paid' && !chosenMethodId"
                            aria-disabled="true"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
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
                    <button type="button" @click="goStep(1)" class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">
                        Back
                    </button>
                    <button type="button" @click="goStep(3)" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700">
                        Continue
                    </button>
                </div>
            </section>

            {{-- STEP 3 — Schedule & Media --}}
            <section x-show="step === 3" x-cloak class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Schedule</h3>

                    <div id="sessions-wrapper" class="mt-4 space-y-4">
                        @if ($existingCount > 0)
                            @foreach ($existingSessions as $i => $s)
                                @php $date = \Carbon\Carbon::parse($s->session_date); @endphp
                                <div class="session-item rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-medium text-gray-700">Session {{ $i + 1 }}</h4>
                                        <button type="button" class="remove-session text-sm text-rose-600 hover:text-rose-700">Remove</button>
                                    </div>

                                    <input type="hidden" name="sessions[{{ $i }}][id]" value="{{ $s->id }}">
                                    <input type="hidden" name="sessions[{{ $i }}][_delete]" value="0">

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-sm text-gray-700 mb-1">Title</label>
                                            <input type="text" name="sessions[{{ $i }}][name]"
                                                   value="{{ old('sessions.'.$i.'.name', $s->session_name) }}"
                                                   class="w-full rounded-lg border-gray-300" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-gray-700 mb-1">Date</label>
                                            <input type="date" name="sessions[{{ $i }}][date]"
                                                   value="{{ old('sessions.'.$i.'.date', $date->format('Y-m-d')) }}"
                                                   class="w-full rounded-lg border-gray-300" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-gray-700 mb-1">Start time</label>
                                            <input type="time" name="sessions[{{ $i }}][time]"
                                                   value="{{ old('sessions.'.$i.'.time', $date->format('H:i')) }}"
                                                   class="w-full rounded-lg border-gray-300" required>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
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
                        @endif
                    </div>

                    <button type="button" id="add-session"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        + Add another session
                    </button>
                </div>

                {{-- Media --}}
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
                    <button type="button" @click="goStep(2)" class="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-gray-700">
                        Back
                    </button>

                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-white hover:bg-indigo-700">
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
        // Alpine state for the edit flow (no legacy ticket cost anywhere)
        document.addEventListener('alpine:init', () => {
            Alpine.data('editEvent', (cfg) => ({
                step: 1,

                currencies: ['GBP','USD','CAD','AUD','INR','NGN','KES','GHS','EUR'],
                pricing: cfg.defaultPaid ? 'paid' : 'free',
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
            let sessionIndex = {{ max($existingCount, 1) }};

            function renumber() {
                const items = wrapper.querySelectorAll('.session-item');
                items.forEach((el, i) => {
                    el.querySelector('h4').textContent = `Session ${i + 1}`;
                    const btn = el.querySelector('.remove-session');
                    if (btn) btn.classList.toggle('hidden', items.length === 1);
                });
            }

            addBtn?.addEventListener('click', () => {
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

        // Ticket types UI add/remove
        (function () {
            const wrap = document.getElementById('cat-rows');
            const tpl  = document.getElementById('cat-tpl')?.innerHTML || '';
            const add  = document.getElementById('add-cat');
            let i = wrap ? wrap.querySelectorAll('.cat-row').length : 0;

            function wireRemove() {
                wrap?.querySelectorAll('.remove-cat').forEach(btn => {
                    btn.onclick = () => btn.closest('.cat-row')?.remove();
                });
            }
            add?.addEventListener('click', () => {
                const html = tpl.replaceAll('__IDX__', `categories[${i++}]`);
                const div = document.createElement('div');
                div.innerHTML = html.trim();
                wrap.appendChild(div.firstElementChild);
                wireRemove();
            });
            wireRemove();
        })();
    </script>

    @if (config('services.google.maps_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initPlaces" async defer></script>
    @endif
</x-app-layout>
