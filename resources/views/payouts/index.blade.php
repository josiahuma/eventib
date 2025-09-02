{{-- resources/views/payouts/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                My payouts
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters + KPIs --}}
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-3">
            {{-- Event filter --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm md:col-span-1">
                <label class="block text-sm text-gray-600 mb-1">Event</label>
                <form method="GET" class="flex items-center gap-2">
                    <select name="event" class="w-full rounded-lg border-gray-300"
                            onchange="this.form.submit()">
                        <option value="">All events</option>
                        @foreach($events as $ev)
                            <option value="{{ $ev->public_id }}" {{ $selectedPublicId === $ev->public_id ? 'selected' : '' }}>
                                {{ $ev->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
                @if(!$selectedEvent)
                    <p class="mt-2 text-xs text-gray-500">Select an event to see per-event totals.</p>
                @endif
            </div>

            {{-- Total requested --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Total amount requested</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">
                    @if($selectedEvent)
                        {{ $symbol }}{{ number_format($totalRequestedMinor/100, 2) }}
                        <span class="text-base text-gray-500">{{ $currency }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ $payouts->total() }} {{ Str::plural('request', $payouts->total()) }} (filtered)
                </div>
            </div>

            {{-- Total paid --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Total amount paid</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">
                    @if($selectedEvent)
                        {{ $symbol }}{{ number_format($totalPaidMinor/100, 2) }}
                        <span class="text-base text-gray-500">{{ $currency }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </div>
                @php
                    $paidCount = $payouts->getCollection()->where('status','paid')->count();
                @endphp
                <div class="text-xs text-gray-500 mt-1">
                    {{ $paidCount }} {{ Str::plural('payment', $paidCount) }} (on this page)
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b text-sm text-gray-600">
                {{ $payouts->total() }} {{ Str::plural('payout', $payouts->total()) }}
                @if($selectedEvent)
                    <span class="ml-1 text-gray-400">for “{{ $selectedEvent->name }}”</span>
                @endif
            </div>

            <div class="divide-y">
                @forelse($payouts as $p)
                    @php
                        $rowCur = strtoupper($p->event->ticket_currency ?? $p->currency ?? 'GBP');
                        $symbols = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
                        $rowSym  = $symbols[$rowCur] ?? '';
                    @endphp
                    <div class="p-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 truncate">
                                {{ $p->event->name ?? 'Event deleted' }}
                            </div>
                            <div class="text-sm text-gray-600">
                                Amount: {{ $rowSym }}{{ number_format($p->amount/100, 2) }}
                                <span class="text-xs text-gray-500">{{ $rowCur }}</span>
                                <span class="mx-2">•</span>
                                Sort code: {{ $p->sort_code ?? '—' }} — Account: {{ $p->account_number ?? '—' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">{{ $p->created_at?->format('d M Y, g:ia') }}</div>
                            <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs
                                @if($p->status === 'paid') bg-emerald-100 text-emerald-700
                                @elseif($p->status === 'cancelled' || $p->status === 'canceled') bg-rose-100 text-rose-700
                                @else bg-amber-100 text-amber-700 @endif">
                                {{ ucfirst($p->status) }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-600">No payout requests yet.</div>
                @endforelse
            </div>

            <div class="p-4">
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
