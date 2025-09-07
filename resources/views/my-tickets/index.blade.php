{{-- resources/views/my-tickets/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My tickets</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                {{ session('error') }}
            </div>
        @endif

        @if($registrations->isEmpty())
            <div class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center">
                <h3 class="text-lg font-semibold text-gray-800">No tickets yet</h3>
                <p class="mt-1 text-sm text-gray-500">When you register for events, they will appear here.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($registrations as $reg)
                    @php
                        $ev = $reg->event;

                        // Determine if this specific registration is paid
                        $statusLower   = strtolower((string)($reg->status ?? ''));
                        $paidStatuses  = ['paid','complete','completed','succeeded'];
                        $amountPaid    = (float)($reg->amount ?? 0);   // major units (you save this in controller)
                        $isPaidReg     = in_array($statusLower, $paidStatuses, true) || $amountPaid > 0;

                        // Hide incomplete paid checkouts (unpaid)
                        if (!$isPaidReg && $statusLower && in_array($statusLower, ['requires_payment','pending','canceled','failed','expired'], true)) {
                            continue;
                        }

                        // Currency -> symbol (prefer registration currency, fallback to event)
                        $cur = strtoupper($reg->currency ?? $ev->ticket_currency ?? 'GBP');
                        $symbols = [
                            'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R',
                            'CAD'=>'$','AUD'=>'$','NZD'=>'$','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'
                        ];
                        $sym = $symbols[$cur] ?? '';

                        // Label: show actual amount paid for this registration; otherwise Free
                        $priceLabel = $isPaidReg
                            ? ($sym ? $sym.number_format($amountPaid, 2) : $cur.' '.number_format($amountPaid, 2))
                            : 'Free';

                        $qty   = max(1, (int)($reg->quantity ?? 1));
                        $ad    = max(0, (int)($reg->party_adults ?? 0));
                        $ch    = max(0, (int)($reg->party_children ?? 0));
                        $party = 1 + $ad + $ch;

                        $statusLabel   = $isPaidReg ? 'Completed' : 'Registered';
                        $statusClasses = $isPaidReg ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800';
                    @endphp

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-gray-900 truncate">{{ $ev->name }}</div>

                                <div class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                                    <span>{{ $priceLabel }}</span>
                                    <span class="text-gray-400">·</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <div class="mt-2 text-sm text-gray-700">
                                    @if($reg->sessions && $reg->sessions->count())
                                        Sessions:
                                        <span class="font-medium">
                                            {{ $reg->sessions->pluck('session_name')->join(', ') }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">No sessions selected</span>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @if($isPaidReg)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">
                                            Tickets: {{ $qty }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                            Party: {{ $party }}
                                        </span>
                                    @endif

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                                        Registered: {{ $reg->created_at?->format('d M Y, g:ia') }}
                                    </span>
                                </div>
                            </div>

                            <div class="shrink-0 flex items-center gap-2">
                                {{-- For paid registrations, don't allow "Manage" (party size editing). --}}
                                @unless($isPaidReg)
                                    <a href="{{ route('my.tickets.edit', $reg->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-sm text-gray-800">
                                        Manage
                                    </a>
                                @endunless

                                @if($isPaidReg)
                                    <a href="{{ route('tickets.first', ['event' => $ev, 'registration' => $reg]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
                                        {{ $qty > 1 ? 'View tickets' : 'View ticket' }}
                                    </a>

                                    {{-- Optional: let user buy more --}}
                                    <a href="{{ route('events.register', $ev) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-sm text-gray-800">
                                        Buy more tickets
                                    </a>
                                @else
                                    <a href="{{ route('tickets.pass', ['event' => $ev, 'registration' => $reg]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm">
                                        View pass
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
