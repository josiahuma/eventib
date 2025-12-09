<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Register for {{ $event->name }}
        </h2>
    </x-slot>

    @php
        $cats    = $event->categories ?? collect();
        $hasCats = $cats->count() > 0;

        $unit     = (float) ($event->ticket_cost ?? 0); // legacy single price (may be 0)
        $currency = strtoupper($event->ticket_currency ?? 'GBP');

        $symbols = [
            'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R',
            'CAD'=>'$','AUD'=>'$','NZD'=>'$','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'
        ];
        $sym = $symbols[$currency] ?? '';

        $min = $hasCats ? (float) $cats->min('price') : null;
        $max = $hasCats ? (float) $cats->max('price') : null;

        $image = $event->banner_url
            ? asset('storage/' . $event->banner_url)
            : ($event->avatar_url ? asset('storage/' . $event->avatar_url) : null);

        $mode = $hasCats ? 'cats' : ($unit > 0 ? 'single' : 'free');

        // Only upcoming sessions
        $upcomingSessions = $event->sessions
            ->filter(fn($s) => \Carbon\Carbon::parse($s->session_date)->isFuture())
            ->sortBy('session_date')
            ->values();
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- alerts --}}
        @if (session('success'))
            <div class="mb-6 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-6 border border-amber-200 bg-amber-50 text-amber-800 rounded-xl p-4">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- main card --}}
        <div class="form-card">
            {{-- ticket summary header --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">
                        Ticket
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">
                        @if($mode === 'cats')
                            @if($min === $max)
                                {{ $sym }}{{ number_format($min, 2) }}
                            @else
                                {{ $sym }}{{ number_format($min, 2) }}–{{ $sym }}{{ number_format($max, 2) }}
                            @endif
                        @elseif($mode === 'single')
                            {{ $sym }}{{ number_format($unit, 2) }}
                        @else
                            Free
                        @endif
                    </div>
                </div>

                @if ($image)
                    <img src="{{ $image }}" alt="" class="h-12 w-12 rounded-md object-cover">
                @endif
            </div>

            {{-- digital pass notice for guests when required --}}
            @if(!auth()->check() && $event->digital_pass_mode === 'required')
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <div class="font-semibold mb-0.5">
                        This event requires an Eventib Digital Pass
                    </div>
                    <p class="text-xs md:text-sm">
                        To register, you’ll need to create an account and set up your Digital Pass
                        (voice/face). Please
                        <a href="{{ route('login') }}" class="underline font-medium">log in</a>
                        or
                        <a href="{{ route('register') }}" class="underline font-medium">create a free account</a>
                        first, then come back to this page.
                    </p>
                </div>
            @endif

            {{-- form --}}
            <form
                action="{{ route('events.register.store', $event) }}"
                method="POST"
                class="mt-6 space-y-6"
                x-data="ticketForm({
                    mode: '{{ $mode }}',
                    sym: '{{ $sym }}',
                    unit: {{ $unit }},
                    cats: @js($cats->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'price' => (float)$c->price])->values()),
                    oldCats: @js(old('categories', [])),
                    initQty: {{ (int) old('quantity', 1) }},
                    initA: {{ (int) old('party_adults', 0) }},
                    initC: {{ (int) old('party_children', 0) }},
                })"
                x-init="init()"
            >
                @csrf

                {{-- Attendee details --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Full name</label>
                        <input
                            type="text"
                            name="name"
                            required
                            value="{{ old('name', optional(auth()->user())->name) }}"
                            class="form-input"
                        >
                        @error('name')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            name="email"
                            required
                            value="{{ old('email', optional(auth()->user())->email) }}"
                            class="form-input"
                        >
                        @if(Auth::check() && !empty($alreadyRegistered))
                            <p class="text-xs text-rose-600 mt-1">You are already registered for this event.</p>
                        @endif
                        @error('email')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Mobile (optional)</label>
                        <input
                            type="text"
                            name="mobile"
                            value="{{ old('mobile') }}"
                            class="form-input"
                        >
                        @error('mobile')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Sessions --}}
                <div>
                    <label class="form-label">Select session(s)</label>
                    <div class="mt-2 space-y-2">
                        @forelse ($upcomingSessions as $s)
                            <label class="flex items-center gap-3 border border-slate-200 rounded-md px-3 py-2 hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="session_ids[]" value="{{ $s->id }}"
                                    class="form-checkbox h-4 w-4"
                                    @checked(in_array($s->id, old('session_ids', [])))
                                >
                                <div>
                                    <div class="text-sm font-medium text-slate-900">
                                        {{ $s->session_name }}
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        {{ \Carbon\Carbon::parse($s->session_date)->format('D, d M Y · g:ia') }}
                                    </div>
                                </div>
                            </label>
                        @empty
                            <p class="text-sm text-slate-600">
                                There are no upcoming sessions available to register for.
                            </p>
                        @endforelse
                    </div>
                    @error('session_ids')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Categories mode --}}
                @if($hasCats)
                    <div x-show="mode === 'cats'">
                        <label class="form-label">Choose tickets</label>
                        <div class="mt-2 space-y-3">
                            @foreach($cats as $c)
                                <div class="flex items-center justify-between border border-slate-200 rounded-md p-3">
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $c->name }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ $sym }}{{ number_format((float)$c->price, 2) }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                            @click="decCat({{ $c->id }})"
                                        >−</button>
                                        <div class="w-6 text-center font-semibold" x-text="catQty[{{ $c->id }}] ?? 0"></div>
                                        <button
                                            type="button"
                                            class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                            @click="incCat({{ $c->id }})"
                                        >+</button>
                                    </div>

                                    <input
                                        type="hidden"
                                        :name="'categories[{{ $c->id }}]'"
                                        :value="catQty[{{ $c->id }}] ?? 0"
                                    >
                                </div>
                            @endforeach
                        </div>

                        @error('categories')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 text-sm text-slate-700">
                            <div>
                                Total items:
                                <span class="font-semibold" x-text="totalQty()"></span>
                            </div>
                            <template x-if="total() > 0">
                                <div>
                                    Total:
                                    <span class="font-semibold" x-text="sym + total().toFixed(2)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                {{-- Single price --}}
                @if(!$hasCats && $unit > 0)
                    <div x-show="mode === 'single'">
                        <label class="form-label">Ticket quantity</label>
                        <div class="mt-2 flex items-center gap-3">
                            <button
                                type="button"
                                class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                @click="dec('qty')"
                            >−</button>
                            <div class="text-lg font-semibold" x-text="qty"></div>
                            <button
                                type="button"
                                class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                @click="inc('qty')"
                            >+</button>
                        </div>

                        <input type="hidden" name="quantity" :value="qty">

                        @error('quantity')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror

                        <div class="mt-2 text-sm text-slate-600">
                            Total:
                            <span class="font-semibold" x-text="sym + (unit * qty).toFixed(2)"></span>
                        </div>
                    </div>
                @endif

                {{-- Free mode --}}
                @if(!$hasCats && $unit == 0)
                    <div x-show="mode === 'free'">
                        <label class="form-label">Who’s coming with you?</label>
                        <p class="form-help">
                            You can add adults and/or children (free events only).
                        </p>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-md border border-slate-200 p-3">
                                <div class="text-sm text-slate-700">Adults</div>
                                <div class="mt-2 flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                        @click="dec('adults')"
                                    >−</button>
                                    <div class="text-lg font-semibold" x-text="adults"></div>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                        @click="inc('adults')"
                                    >+</button>
                                </div>
                                <input type="hidden" name="party_adults" :value="adults">
                                @error('party_adults')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="rounded-md border border-slate-200 p-3">
                                <div class="text-sm text-slate-700">Children</div>
                                <div class="mt-2 flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                        @click="dec('children')"
                                    >−</button>
                                    <div class="text-lg font-semibold" x-text="children"></div>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1.5 text-sm border border-slate-300 rounded-md"
                                        @click="inc('children')"
                                    >+</button>
                                </div>
                                <input type="hidden" name="party_children" :value="children">
                                @error('party_children')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3 text-sm text-slate-600">
                            Total attendee number:
                            <span class="font-medium" x-text="1 + adults + children"></span>
                        </div>
                    </div>
                @endif

                {{-- Digital pass section for logged-in users --}}
                @if(auth()->check())
                    @php
                        $dp         = auth()->user()->digitalPass;
                        $dpRequired = $event->digital_pass_mode === 'required';
                    @endphp

                    @if($dp && $dp->is_active)
                        <div class="border border-slate-200 rounded-md bg-slate-50 px-3 py-3">
                            <label class="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    name="use_digital_pass"
                                    value="1"
                                    class="form-checkbox mt-1"
                                    @checked(old('use_digital_pass'))
                                >
                                <span>
                                    <span class="font-medium text-slate-900">
                                        Use my Digital Pass for check-in
                                    </span><br>
                                    @if($dpRequired)
                                        <span class="text-xs text-rose-600 font-medium">
                                            This event requires a Digital Pass. Tick this box to continue.
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500">
                                            Use your voice/face pass instead of only scanning a QR code at the venue.
                                        </span>
                                    @endif
                                </span>
                            </label>

                            @error('use_digital_pass')
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div class="border border-slate-200 rounded-md bg-slate-50 px-3 py-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-slate-900">
                                    Set up your Digital Pass (optional)
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    Create a secure voice/face pass once and reuse it to check in to supported events.
                                </div>
                            </div>
                            <a
                                href="{{ route('digital-pass.setup') }}"
                                class="form-primary-btn px-3 py-1.5 text-xs"
                            >
                                Setup
                            </a>
                        </div>
                    @endif
                @endif

                {{-- footer actions --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a
                        href="{{ route('events.show', $event) }}"
                        class="form-secondary-btn px-4 py-2"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        :disabled="mode==='cats' && totalQty()===0"
                        class="form-primary-btn disabled:opacity-50"
                    >
                        <span x-text="submitLabel()"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Alpine ticketForm stays the same --}}
    <script>
        function ticketForm(cfg) {
            const MAX = 10;
            return {
                mode: cfg.mode,
                sym: cfg.sym || '',
                unit: cfg.unit || 0,
                qty: Math.max(1, Math.min(MAX, cfg.initQty || 1)),
                adults: Math.max(0, Math.min(20, cfg.initA || 0)),
                children: Math.max(0, Math.min(20, cfg.initC || 0)),
                cats: cfg.cats || [],
                catQty: {},

                // Voice Pass state (unchanged)
                voiceEnabled: false,
                isRecording: false,
                hasVoicePreview: false,
                voicePreviewUrl: '',
                recordingError: '',
                mediaRecorder: null,
                audioChunks: [],

                init() {
                    const old = cfg.oldCats || {};
                    this.cats.forEach(c => {
                        const v = parseInt(old[c.id] ?? 0, 10);
                        this.catQty[c.id] = Number.isFinite(v) ? Math.max(0, v) : 0;
                    });
                },

                inc(what) {
                    if (what === 'qty') this.qty = Math.min(MAX, this.qty + 1);
                    if (what === 'adults') this.adults = Math.min(20, this.adults + 1);
                    if (what === 'children') this.children = Math.min(20, this.children + 1);
                },
                dec(what) {
                    if (what === 'qty') this.qty = Math.max(1, this.qty - 1);
                    if (what === 'adults') this.adults = Math.max(0, this.adults - 1);
                    if (what === 'children') this.children = Math.max(0, this.children - 1);
                },

                incCat(id) { this.catQty[id] = (this.catQty[id] || 0) + 1; },
                decCat(id) { this.catQty[id] = Math.max(0, (this.catQty[id] || 0) - 1); },
                totalQty() {
                    if (this.mode !== 'cats') return this.qty;
                    return this.cats.reduce((n, c) => n + (this.catQty[c.id] || 0), 0);
                },
                total() {
                    if (this.mode !== 'cats') return this.unit * this.qty;
                    return this.cats.reduce((sum, c) => sum + (c.price * (this.catQty[c.id] || 0)), 0);
                },
                submitLabel() {
                    if (this.mode === 'cats') return this.total() > 0 ? 'Proceed to Payment' : 'Register';
                    return this.unit > 0 ? 'Proceed to Payment' : 'Register';
                },

                toggleVoiceEnabled() {
                    this.voiceEnabled = !this.voiceEnabled;
                    if (!this.voiceEnabled) {
                        this.resetVoicePass();
                    }
                },

                async startVoiceRecording() {
                    this.recordingError = '';

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.recordingError = 'Your browser does not support audio recording.';
                        return;
                    }

                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.audioChunks = [];
                        this.mediaRecorder = new MediaRecorder(stream);

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data && e.data.size > 0) {
                                this.audioChunks.push(e.data);
                            }
                        };

                        this.mediaRecorder.onstop = () => {
                            const blob = new Blob(this.audioChunks, { type: 'audio/webm' });

                            if (this.voicePreviewUrl) {
                                URL.revokeObjectURL(this.voicePreviewUrl);
                            }
                            this.voicePreviewUrl = URL.createObjectURL(blob);
                            this.hasVoicePreview = true;

                            const reader = new FileReader();
                            reader.onloadend = () => {
                                if (this.$refs.voiceBlob) {
                                    this.$refs.voiceBlob.value = reader.result;
                                }
                            };
                            reader.readAsDataURL(blob);

                            if (this.mediaRecorder && this.mediaRecorder.stream) {
                                this.mediaRecorder.stream.getTracks().forEach(t => t.stop());
                            }
                        };

                        this.mediaRecorder.start();
                        this.isRecording = true;
                    } catch (err) {
                        console.error(err);
                        this.recordingError = 'Could not access microphone. Please check your permissions.';
                    }
                },

                stopVoiceRecording() {
                    if (this.mediaRecorder && this.isRecording) {
                        this.mediaRecorder.stop();
                        this.isRecording = false;
                    }
                },

                resetVoicePass() {
                    this.stopVoiceRecording();
                    this.audioChunks = [];
                    this.hasVoicePreview = false;
                    this.recordingError = '';

                    if (this.voicePreviewUrl) {
                        URL.revokeObjectURL(this.voicePreviewUrl);
                        this.voicePreviewUrl = '';
                    }

                    if (this.$refs.voiceBlob) {
                        this.$refs.voiceBlob.value = '';
                    }
                },
            }
        }
    </script>
</x-app-layout>
