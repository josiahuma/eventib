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
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-6 p-4 rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Ticket</div>
                    <div class="text-2xl font-bold text-gray-900">
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
                    <img src="{{ $image }}" alt="" class="h-12 w-12 rounded-lg object-cover">
                @endif
            </div>

            <form action="{{ route('events.register.store', $event) }}"
                  method="POST"
                  class="mt-6"
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
                  x-init="init()">
                @csrf

                {{-- Attendee details --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full name</label>
                        <input type="text" name="name"
                               value="{{ old('name', optional(auth()->user())->name) }}"
                               class="mt-1 w-full rounded-lg border-gray-300" required>
                        @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email"
                               value="{{ old('email', optional(auth()->user())->email) }}"
                               class="mt-1 w-full rounded-lg border-gray-300" required>
                        {{-- This flag is set ONLY for free events in the controller --}}
                        @if(Auth::check() && !empty($alreadyRegistered))                            
                            <p class="text-sm text-rose-600 mt-1">You are already registered for this event.</p>
                        @endif
                        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Mobile (optional)</label>
                        <input type="text" name="mobile" value="{{ old('mobile') }}"
                               class="mt-1 w-full rounded-lg border-gray-300">
                        @error('mobile') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Sessions --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700">Select session(s)</label>
                    <div class="mt-2 space-y-2">
                        @forelse ($event->sessions as $s)
                            <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="session_ids[]" value="{{ $s->id }}"
                                       class="h-4 w-4 rounded border-gray-300"
                                       @checked(in_array($s->id, old('session_ids', [])))>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $s->session_name }}</div>
                                    <div class="text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($s->session_date)->format('D, d M Y · g:ia') }}
                                    </div>
                                </div>
                            </label>
                        @empty
                            <p class="text-sm text-gray-600">No sessions yet.</p>
                        @endforelse
                    </div>
                    @error('session_ids') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Categories mode --}}
                @if($hasCats)
                    <div class="mt-6" x-show="mode === 'cats'">
                        <label class="block text-sm font-medium text-gray-700">Choose tickets</label>
                        <div class="mt-2 space-y-3">
                            @foreach($cats as $c)
                                <div class="flex items-center justify-between border rounded-lg p-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $c->name }}</div>
                                        <div class="text-xs text-gray-600">{{ $sym }}{{ number_format((float)$c->price, 2) }}</div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100"
                                                @click="decCat({{ $c->id }})">−</button>
                                        <div class="w-6 text-center font-semibold" x-text="catQty[{{ $c->id }}] ?? 0"></div>
                                        <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100"
                                                @click="incCat({{ $c->id }})">+</button>
                                    </div>

                                    <input type="hidden" :name="'categories[{{ $c->id }}]'" :value="catQty[{{ $c->id }}] ?? 0">
                                </div>
                            @endforeach
                        </div>
                        @error('categories') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

                        <div class="mt-3 text-sm text-gray-700">
                            <div>Total items: <span class="font-semibold" x-text="totalQty()"></span></div>
                            <template x-if="total() > 0">
                                <div>Total: <span class="font-semibold" x-text="sym + total().toFixed(2)"></span></div>
                            </template>
                        </div>
                    </div>
                @endif

                {{-- Single price --}}
                @if(!$hasCats && $unit > 0)
                    <div class="mt-6" x-show="mode === 'single'">
                        <label class="block text-sm font-medium text-gray-700">Ticket quantity</label>
                        <div class="mt-2 flex items-center gap-3">
                            <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="dec('qty')">−</button>
                            <div class="text-lg font-semibold" x-text="qty"></div>
                            <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="inc('qty')">+</button>
                        </div>
                        <input type="hidden" name="quantity" :value="qty">
                        @error('quantity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

                        <div class="mt-2 text-sm text-gray-600">
                            Total: <span class="font-semibold" x-text="sym + (unit * qty).toFixed(2)"></span>
                        </div>
                    </div>
                @endif

                {{-- Free mode --}}
                @if(!$hasCats && $unit == 0)
                    <div class="mt-6" x-show="mode === 'free'">
                        <label class="block text-sm font-medium text-gray-700">Who’s coming with you?</label>
                        <p class="text-xs text-gray-500 mt-1">You can add adults and/or children (free events only).</p>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-lg border p-3">
                                <div class="text-sm text-gray-700">Adults</div>
                                <div class="mt-2 flex items-center gap-3">
                                    <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="dec('adults')">−</button>
                                    <div class="text-lg font-semibold" x-text="adults"></div>
                                    <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="inc('adults')">+</button>
                                </div>
                                <input type="hidden" name="party_adults" :value="adults">
                                @error('party_adults') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-lg border p-3">
                                <div class="text-sm text-gray-700">Children</div>
                                <div class="mt-2 flex items-center gap-3">
                                    <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="dec('children')">−</button>
                                    <div class="text-lg font-semibold" x-text="children"></div>
                                    <button type="button" class="px-2.5 py-1.5 rounded-lg bg-gray-100" @click="inc('children')">+</button>
                                </div>
                                <input type="hidden" name="party_children" :value="children">
                                @error('party_children') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-3 text-sm text-gray-600">
                            Total attendee number: <span class="font-medium" x-text="1 + adults + children"></span>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('events.show', $event) }}" class="text-sm text-gray-600 hover:text-gray-800">Cancel</a>
                    <button type="submit"
                            :disabled="mode==='cats' && totalQty()===0"
                            class="inline-flex items-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50">
                        <span x-text="submitLabel()"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

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
                }
            }
        }
    </script>
</x-app-layout>
