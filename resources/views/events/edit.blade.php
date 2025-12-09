{{-- resources/views/events/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Event — {{ $event->name }}
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">
                Back to dashboard
            </a>
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

        // Fee mode + initial payout method (used in step 1 + Alpine config)
        $feeMode = old('fee_mode', $event->fee_mode ?? 'absorb');
        $initialPayoutMethodId = old('payout_method_id', $event->payout_method_id);

        // 🔐 Digital pass defaults for edit form
        $dpMode    = old('digital_pass_mode', $event->digital_pass_mode ?? 'off');    // off|optional|required
        $dpMethods = old('digital_pass_methods', $event->digital_pass_methods ?? 'both'); // voice|face|both
    @endphp

    <div
        x-data="editEvent({
            methods: @js($rawMethods),
            defaultCurrency: '{{ $initialCurrency }}',
            defaultPaid: {{ $initialPaid ? 'true' : 'false' }},
            profilePayoutUrl: '{{ route('profile.payouts') }}',
            defaultPayoutMethodId: {{ $initialPayoutMethodId ? (int) $initialPayoutMethodId : 'null' }}
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

        {{-- Top nav, same as create --}}
        <div class="mb-6 border-b border-slate-200">
            <nav class="flex gap-8 text-sm">
                <button type="button"
                        @click="goStep(1)"
                        :class="step === 1
                            ? 'text-indigo-600 border-b-2 border-indigo-600 pb-3 -mb-px font-semibold'
                            : 'text-slate-500 hover:text-slate-800 pb-3'">
                    1) Pricing & payout
                </button>

                <button type="button"
                        @click="goStep(2)"
                        :class="step === 2
                            ? 'text-indigo-600 border-b-2 border-indigo-600 pb-3 -mb-px font-semibold'
                            : 'text-slate-500 hover:text-slate-800 pb-3'">
                    2) Basics
                </button>

                <button type="button"
                        @click="goStep(3)"
                        :class="step === 3
                            ? 'text-indigo-600 border-b-2 border-indigo-600 pb-3 -mb-px font-semibold'
                            : 'text-slate-500 hover:text-slate-800 pb-3'">
                    3) Schedule & media
                </button>
            </nav>
        </div>

        <form action="{{ route('events.update', $event) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- keep fee_mode + currency fixed via hidden inputs --}}
            <input type="hidden" name="fee_mode" value="{{ $feeMode }}">
            <input type="hidden" name="ticket_currency" value="{{ $event->ticket_currency }}">

            {{-- STEP 1 — Pricing & Payout --}}
            <section x-show="step === 1" x-cloak class="space-y-6">
                <div class="form-card">
                    <div class="flex items-start justify-between">
                        <h3 class="form-section-title">Pricing & payout</h3>
                        <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2a5 5 0 00-5 5v3H6a2 2 0 00-2 2v7a2 2 0 002 2h12a2 2 0 002-2v-7a2 2 0 00-2-2h-1V7a5 5 0 00-5-5zm-3 8V7a3 3 0 116 0v3H9z"/>
                            </svg>
                            Pricing locked
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-slate-600">
                        You can change <strong>capacity</strong> and <strong>payout destination</strong> at any time.
                        Pricing type (free vs paid), currency and ticket amounts stay fixed after creation.
                    </p>

                    <div class="mt-4 grid gap-3 text-sm text-slate-700">
                        <div>
                            <span class="font-medium">Event type:</span>
                            @if($initialPaid)
                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                    Paid
                                </span>
                            @else
                                <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                    Free
                                </span>
                            @endif
                        </div>

                        @if($initialPaid)
                            <div>
                                <span class="font-medium">Currency:</span>
                                <span class="ml-1">{{ strtoupper($event->ticket_currency ?? 'GBP') }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Platform fee mode:</span>
                                <span class="ml-1">
                                    {{ $feeMode === 'pass' ? 'Pass fee to attendees' : 'Organiser absorbs fee' }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Maximum attendees (optional)</label>
                            <input
                                type="number"
                                min="0"
                                name="capacity"
                                value="{{ old('capacity', $event->capacity) }}"
                                class="form-input"
                                placeholder="e.g., 150"
                            >
                            <p class="form-help">
                                Leave blank for unlimited. Free events will stop accepting registrations when this
                                limit is reached.
                            </p>
                        </div>
                    </div>

                    @if($initialPaid)
                        <div class="mt-6 border-t border-slate-200 pt-4">
                            <h4 class="text-sm font-semibold text-slate-900">Payout destination</h4>

                            <template x-if="eligibleMethods.length">
                                <div class="mt-3 grid grid-cols-1 gap-3">
                                    <template x-for="m in eligibleMethods" :key="m.id">
                                        <label
                                            class="cursor-pointer rounded-xl border p-3 flex items-start gap-3"
                                            :class="chosenMethodId === m.id ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 bg-white'">
                                            <input
                                                type="radio"
                                                class="mt-1 form-radio"
                                                name="payout_method_id"
                                                :value="m.id"
                                                x-model="chosenMethodId">
                                            <div>
                                                <div class="font-medium text-slate-900" x-text="methodTitle(m)"></div>
                                                <div class="text-sm text-slate-600" x-text="methodSubtitle(m)"></div>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </template>

                            <div
                                class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 text-xs"
                                x-show="pricing === 'paid' && !eligibleMethods.length">
                                No payout method saved for
                                <span class="font-semibold" x-text="country"></span>.
                                <a class="underline" :href="profilePayoutUrl + '?country=' + country" target="_blank">
                                    Add one now
                                </a>, then refresh this page.
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <span></span>
                    <button type="button"
                            @click="goStep(2)"
                            class="form-primary-btn">
                        Continue
                    </button>
                </div>
            </section>

            {{-- STEP 2 — Basics + Digital Pass --}}
            <section x-show="step === 2" x-cloak class="space-y-6">
                {{-- Basics --}}
                <div class="form-card">
                    <h3 class="form-section-title">Basics</h3>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label">Event name</label>
                            <input type="text" name="name" required
                                   value="{{ old('name', $event->name) }}"
                                   class="form-input">
                        </div>

                        <div>
                            <label class="form-label">Organizer</label>
                            <select name="organizer_id" required class="form-select">
                                <option value="">— Select organizer —</option>
                                @foreach($organizers as $organizer)
                                    <option value="{{ $organizer->id }}"
                                        @selected(old('organizer_id', $event->organizer_id) == $organizer->id)>
                                        {{ $organizer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">— Select —</option>
                                @foreach ($cats as $cat)
                                    <option value="{{ $cat }}" @selected(old('category', $event->category) === $cat)>
                                        {{ $cat }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="form-label">Tags</label>
                            <select id="tags" name="tags[]" multiple class="form-select">
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag }}" selected>{{ $tag }}</option>
                                @endforeach
                            </select>
                            <p class="form-help">Type and press enter to add.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="form-label">Location</label>
                            <input id="location-input" type="text" name="location"
                                   value="{{ old('location', $event->location) }}"
                                   class="form-input"
                                   placeholder="Venue, address or place name" autocomplete="off">
                            <input type="hidden" name="location_place_id" id="location_place_id" value="{{ old('location_place_id', $event->location_place_id ?? '') }}">
                            <input type="hidden" name="location_lat" id="location_lat" value="{{ old('location_lat', $event->location_lat ?? '') }}">
                            <input type="hidden" name="location_lng" id="location_lng" value="{{ old('location_lng', $event->location_lng ?? '') }}">
                            <p class="form-help">Start typing and choose a suggestion.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="form-label">Description</label>

                            <div class="border border-slate-200 rounded-none overflow-hidden mb-2 bg-white">
                                <div id="desc-toolbar" class="border-b bg-slate-50 px-2 py-1 text-sm">
                                    <span class="ql-formats">
                                        <button class="ql-bold"></button>
                                        <button class="ql-italic"></button>
                                        <button class="ql-underline"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <button class="ql-list" value="ordered"></button>
                                        <button class="ql-list" value="bullet"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <select class="ql-header">
                                            <option selected></option>
                                            <option value="2"></option>
                                            <option value="3"></option>
                                        </select>
                                        <button class="ql-link"></button>
                                        <button class="ql-blockquote"></button>
                                    </span>
                                </div>

                                <input type="hidden" name="description" id="desc-html"
                                       value="{{ old('description', $event->description) }}">
                                <div id="desc-editor" class="min-h-[180px] bg-white overflow-y-auto"></div>
                            </div>

                            <p class="form-help">
                                Format text, add links and lists. We’ll save the formatted content.
                            </p>
                        </div>
                    </div>

                    {{-- 🔐 Digital Pass (same design as create) --}}
                    <div class="mt-6 border-t border-slate-200 pt-6">
                        <h4 class="text-sm font-semibold text-slate-900">
                            Digital Pass for this event
                        </h4>
                        <p class="mt-1 text-xs text-slate-500">
                            Decide whether attendees can or must use their Eventib Digital Pass for check-in.
                        </p>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            {{-- Off --}}
                            <label class="flex items-start gap-2 border px-3 py-3 cursor-pointer rounded-none
                                          {{ $dpMode === 'off' ? 'border-indigo-500 bg-indigo-50/60' : 'border-slate-200' }}">
                                <input type="radio" name="digital_pass_mode" value="off"
                                       class="mt-1 form-radio"
                                       {{ $dpMode === 'off' ? 'checked' : '' }}>
                                <div>
                                    <div class="font-medium text-slate-900">Not used</div>
                                    <p class="text-xs text-slate-500">
                                        Normal QR / manual check-in only.
                                    </p>
                                </div>
                            </label>

                            {{-- Optional --}}
                            <label class="flex items-start gap-2 border px-3 py-3 cursor-pointer rounded-none
                                          {{ $dpMode === 'optional' ? 'border-indigo-500 bg-indigo-50/60' : 'border-slate-200' }}">
                                <input type="radio" name="digital_pass_mode" value="optional"
                                       class="mt-1 form-radio"
                                       {{ $dpMode === 'optional' ? 'checked' : '' }}>
                                <div>
                                    <div class="font-medium text-slate-900">Optional</div>
                                    <p class="text-xs text-slate-500">
                                        Attendees can opt-in to use their voice / face pass.
                                    </p>
                                </div>
                            </label>

                            {{-- Required --}}
                            <label class="flex items-start gap-2 border px-3 py-3 cursor-pointer rounded-none
                                          {{ $dpMode === 'required' ? 'border-indigo-500 bg-indigo-50/60' : 'border-slate-200' }}">
                                <input type="radio" name="digital_pass_mode" value="required"
                                       class="mt-1 form-radio"
                                       {{ $dpMode === 'required' ? 'checked' : '' }}>
                                <div>
                                    <div class="font-medium text-slate-900">Required</div>
                                    <p class="text-xs text-slate-500">
                                        Only attendees with an active Digital Pass can register.
                                    </p>
                                </div>
                            </label>
                        </div>

                        @error('digital_pass_mode')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4">
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Allowed Digital Pass methods
                            </label>
                            <div class="flex flex-wrap gap-4 text-sm">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="digital_pass_methods" value="voice"
                                           class="form-radio"
                                           {{ $dpMethods === 'voice' ? 'checked' : '' }}>
                                    <span>Voice only</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="digital_pass_methods" value="face"
                                           class="form-radio"
                                           {{ $dpMethods === 'face' ? 'checked' : '' }}>
                                    <span>Face only</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="digital_pass_methods" value="both"
                                           class="form-radio"
                                           {{ $dpMethods === 'both' ? 'checked' : '' }}>
                                    <span>Voice or face</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                We’ll enforce this later on the check-in screen. For now it’s stored with the event.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button" @click="goStep(1)" class="form-secondary-btn">
                        Back
                    </button>
                    <button type="button" @click="goStep(3)" class="form-primary-btn">
                        Continue
                    </button>
                </div>
            </section>

            {{-- STEP 3 — Schedule & Media --}}
            <section x-show="step === 3" x-cloak class="space-y-6">
                {{-- Schedule --}}
                <div class="form-card">
                    <h3 class="form-section-title">Schedule</h3>

                    <div class="mt-3 space-y-3">
                        <label class="inline-flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="is_recurring"
                                value="1"
                                class="form-checkbox"
                                {{ old('is_recurring', $event->is_recurring) ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">This is a recurring event</span>
                        </label>

                        <div>
                            <label class="form-label">Recurrence pattern (optional)</label>
                            <input
                                type="text"
                                name="recurrence_summary"
                                value="{{ old('recurrence_summary', $event->recurrence_summary) }}"
                                class="form-input"
                                placeholder="e.g., Every 1st Saturday of every month">
                            <p class="form-help">
                                This is only for display. Add actual dates/times below as sessions.
                            </p>
                        </div>
                    </div>

                    <div id="sessions-wrapper" class="mt-4 space-y-4">
                        @if ($existingCount > 0)
                            @foreach ($existingSessions as $i => $s)
                                @php $date = \Carbon\Carbon::parse($s->session_date); @endphp
                                <div class="session-item border border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-medium text-slate-700">Session {{ $i + 1 }}</h4>
                                        <button type="button" class="text-sm text-rose-600 hover:text-rose-700 remove-session">
                                            Remove
                                        </button>
                                    </div>

                                    <input type="hidden" name="sessions[{{ $i }}][id]" value="{{ $s->id }}">
                                    <input type="hidden" name="sessions[{{ $i }}][_delete]" value="0">

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="form-label">Title</label>
                                            <input type="text"
                                                   name="sessions[{{ $i }}][name]"
                                                   value="{{ old('sessions.'.$i.'.name', $s->session_name) }}"
                                                   class="form-input"
                                                   required>
                                        </div>

                                        <div>
                                            <label class="form-label">Date</label>
                                            <input type="date"
                                                   name="sessions[{{ $i }}][date]"
                                                   value="{{ old('sessions.'.$i.'.date', $date->format('Y-m-d')) }}"
                                                   class="form-input"
                                                   required>
                                        </div>

                                        <div>
                                            <label class="form-label">Start time</label>
                                            <input type="time"
                                                   name="sessions[{{ $i }}][time]"
                                                   value="{{ old('sessions.'.$i.'.time', $date->format('H:i')) }}"
                                                   class="form-input"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="session-item border border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-slate-700">Session 1</h4>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 remove-session hidden">
                                        Remove
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="form-label">Title</label>
                                        <input type="text" name="sessions[0][name]" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Date</label>
                                        <input type="date" name="sessions[0][date]" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Start time</label>
                                        <input type="time" name="sessions[0][time]" class="form-input" required>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <button type="button" id="add-session" class="mt-4 form-primary-btn">
                        + Add another session
                    </button>
                </div>

                {{-- Media --}}
                <div class="form-card">
                    <h3 class="form-section-title">Media</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        {{-- Banner --}}
                        <div>
                            <label class="form-label">Event banner (replace)</label>
                            <p class="form-help mb-2">
                                Recommended 1200×300 (4:1). Upload a new file to replace the current banner.
                            </p>

                            <label class="file-input-modern">
                                <span class="file-label">Choose banner</span>
                                <input type="file" name="banner" accept="image/*" class="hidden">
                                <span class="file-filename text-xs text-slate-500">No file chosen</span>
                            </label>

                            @if ($event->banner_url)
                                <div class="mt-3 flex items-center gap-3 text-xs text-slate-600">
                                    <img src="{{ asset('storage/'.$event->banner_url) }}"
                                         alt="banner"
                                         class="h-12 w-20 object-cover border border-slate-200">
                                    <span class="underline truncate">
                                        {{ $event->banner_url }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Avatar --}}
                        <div>
                            <label class="form-label">Event avatar (replace)</label>

                            <label class="file-input-modern">
                                <span class="file-label">Choose avatar</span>
                                <input type="file" name="avatar" accept="image/*" class="hidden">
                                <span class="file-filename text-xs text-slate-500">No file chosen</span>
                            </label>

                            @if ($event->avatar_url)
                                <div class="mt-3 flex items-center gap-3 text-xs text-slate-600">
                                    <img src="{{ asset('storage/'.$event->avatar_url) }}"
                                         alt="avatar"
                                         class="h-10 w-10 object-cover border border-slate-200">
                                    <span class="underline truncate">
                                        {{ $event->avatar_url }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button" @click="goStep(2)" class="form-secondary-btn">
                        Back
                    </button>

                    <button type="submit" class="form-primary-btn bg-indigo-600 hover:bg-indigo-700">
                        Save Changes
                    </button>
                </div>
            </section>
        </form>
    </div>

    {{-- Tom Select --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    {{-- Quill assets --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

    <style>
        /* Quill editor */
        #desc-editor .ql-editor {
            min-height: 160px;
            max-height: 400px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            color: #111827; /* text-gray-900 */
        }
        #desc-editor .ql-editor.ql-blank::before {
            color: #9ca3af; /* text-gray-400 */
            font-style: italic;
            content: attr(data-placeholder);
        }

        /* Modern flat file input */
        .file-input-modern {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.5rem 0.75rem;           /* px-3 py-2 */
            border: 1px solid #cbd5e1;         /* slate-300 */
            background-color: #f8fafc;         /* slate-50 */
            font-size: 0.875rem;               /* text-sm */
            cursor: pointer;
            box-sizing: border-box;
            gap: 0.75rem;
        }

        .file-input-modern .file-label {
            font-size: 0.75rem;                /* text-xs */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f172a;                    /* slate-900-ish */
            white-space: nowrap;
        }

        .file-input-modern .file-filename {
            font-size: 0.75rem;
            color: #6b7280;                    /* gray-500 */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            text-align: right;
        }
    </style>


    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('editEvent', (cfg) => ({
                step: 1,
                currencies: ['GBP','USD','CAD','AUD','INR','NGN','KES','GHS','EUR'],
                pricing: cfg.defaultPaid ? 'paid' : 'free',
                currency: (cfg.defaultCurrency || 'GBP').toUpperCase(),
                country: '',
                allMethods: cfg.methods || [],
                eligibleMethods: [],
                chosenMethodId: cfg.defaultPayoutMethodId || null,
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
                    if (this.pricing !== 'paid') {
                        this.eligibleMethods = [];
                        this.chosenMethodId = null;
                        return;
                    }

                    const banks  = this.allMethods.filter(
                        m => m.type === 'bank' && (m.country || '').toUpperCase() === this.country
                    );
                    const paypal = this.allMethods.find(m => m.type === 'paypal');
                    this.eligibleMethods = paypal ? [...banks, paypal] : banks;

                    if (!this.eligibleMethods.length) {
                        this.chosenMethodId = null;
                        return;
                    }

                    const stillValid = this.eligibleMethods.find(m => m.id === this.chosenMethodId);
                    if (!stillValid) {
                        this.chosenMethodId = this.eligibleMethods[0].id;
                    }
                },

                methodTitle(m)   { return m.type === 'bank' ? `Bank — ${m.country}` : 'PayPal'; },
                methodSubtitle(m){ return m.type === 'bank'
                                        ? (m.last4 ? `${m.label} — ****${m.last4}` : m.label)
                                        : (m.email || m.label); },

                goStep(n) {
                    this.step = n;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }));
        });

        document.addEventListener("DOMContentLoaded", function () {
            // Tom Select
            new TomSelect("#tags", {
                plugins: ['remove_button'],
                persist: false,
                create: true,
                createOnBlur: true,
                placeholder: "Add tags…",
                delimiter: ',',
            });

            // Quill
            const toolbar = document.getElementById('desc-toolbar');
            const editor  = document.getElementById('desc-editor');
            const hidden  = document.getElementById('desc-html');
            if (toolbar && editor && hidden && window.Quill) {
                const quill = new Quill(editor, {
                    theme: 'snow',
                    placeholder: 'Tell people what to expect',
                    modules: { toolbar: toolbar },
                });

                const existing = hidden.value || '';
                if (existing.trim() !== '') {
                    quill.clipboard.dangerouslyPasteHTML(existing);
                }

                quill.on('text-change', function () {
                    hidden.value = quill.root.innerHTML.trim();
                });
            }

            // File input filename display
            document.querySelectorAll('.file-input-modern input[type="file"]').forEach(input => {
                input.addEventListener('change', () => {
                    const span = input.parentElement.querySelector('.file-filename');
                    span.textContent = input.files.length ? input.files[0].name : 'No file chosen';
                });
            });
        });

        // Sessions add/remove – same flat design as create
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
                <div class="session-item border border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-slate-700">Session</h4>
                        <button type="button" class="text-sm text-rose-600 hover:text-rose-700 remove-session">Remove</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">Title</label>
                            <input type="text" name="sessions[${sessionIndex}][name]" class="form-input" required placeholder="e.g., Workshop">
                        </div>
                        <div>
                            <label class="form-label">Date</label>
                            <input type="date" name="sessions[${sessionIndex}][date]" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Start time</label>
                            <input type="time" name="sessions[${sessionIndex}][time]" class="form-input" required>
                        </div>
                    </div>
                </div>`;
                wrapper.insertAdjacentHTML('beforeend', html);
                sessionIndex++;
                renumber();
            });

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.remove-session');
                if (!btn || !wrapper.contains(btn)) return;
                const item = btn.closest('.session-item');
                const del  = item.querySelector('input[name$="[_delete]"]');
                if (del) {
                    del.value = '1';
                    item.style.display = 'none';
                } else {
                    item.remove();
                }
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
