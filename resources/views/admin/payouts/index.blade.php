{{-- resources/views/admin/payouts/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin · Payouts</h2>
            <div class="text-sm">
                <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-800 underline">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                <ul class="list-disc ms-5 text-sm">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Filters / summary --}}
        <div class="grid grid-cols-4 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <form method="GET" class="grid grid-cols-1 gap-2">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach (['processing','paid','failed','cancelled'] as $opt)
                                <option value="{{ $opt }}" {{ request('status')===$opt ? 'selected' : '' }}>
                                    {{ ucfirst($opt) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Search (event / user)</label>
                        <div class="flex">
                            <input type="text" name="q" value="{{ request('q') }}" class="w-full rounded-l-lg border-gray-300" placeholder="Type and press enter">
                            <button class="px-3 rounded-r-lg border border-l-0 bg-gray-50">Go</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Total (filtered)</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($totalCount) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Processing</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($processingCnt) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Paid / Failed</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format($paidCnt) }} <span class="text-gray-400">/</span> {{ number_format($failedCnt) }}
                </div>
                @if($cancelledCnt > 0)
                    <div class="text-xs text-gray-500 mt-1">Cancelled: {{ number_format($cancelledCnt) }}</div>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b text-sm text-gray-600">
                {{ $payouts->total() }} {{ Str::plural('payout', $payouts->total()) }}
                @if(request('status')) <span class="text-gray-400"> · {{ ucfirst(request('status')) }}</span>@endif
                @if(request('q')) <span class="text-gray-400"> · “{{ request('q') }}”</span>@endif
            </div>

            <div class="divide-y">
                @forelse($payouts as $p)
                    @php
                        $cur = strtoupper($p->event->ticket_currency ?? $p->currency ?? 'GBP');
                        $symbols = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
                        $sym = $symbols[$cur] ?? '';
                        $maskedSort = $p->sort_code ? str_repeat('•', max(0, strlen($p->sort_code) - 2)).substr($p->sort_code, -2) : '—';
                        $maskedAcct = $p->account_number ? '••••'.substr($p->account_number, -4) : '—';
                    @endphp

                    <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                        <div class="md:col-span-4 min-w-0">
                            <div class="font-medium text-gray-900 truncate">
                                {{ $p->event->name ?? 'Event deleted' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                Organizer: {{ $p->event->user->name ?? '—' }} · {{ $p->event->user->email ?? '—' }}
                            </div>
                        </div>

                        <div class="md:col-span-3 text-sm text-gray-700">
                            <div>Amount: <span class="font-medium">{{ $sym }}{{ number_format($p->amount/100, 2) }}</span> <span class="text-xs text-gray-500">{{ $cur }}</span></div>
                            <div class="text-xs text-gray-500">Sort: {{ $maskedSort }} · Acct: {{ $maskedAcct }}</div>
                        </div>

                        <div class="md:col-span-2 text-sm">
                            <div class="text-gray-500">Requested</div>
                            <div class="font-medium text-gray-900">{{ $p->created_at?->format('d M Y, g:ia') }}</div>
                            @if($p->paid_at)
                                <div class="text-gray-500 mt-1 text-xs">Paid at: {{ $p->paid_at->format('d M Y, g:ia') }}</div>
                            @endif
                        </div>

                        <div class="md:col-span-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
                                @if($p->status === 'paid') bg-emerald-100 text-emerald-700
                                @elseif($p->status === 'failed') bg-rose-100 text-rose-700
                                @elseif($p->status === 'processing') bg-amber-100 text-amber-700
                                @else bg-gray-100 text-gray-700 @endif">
                                {{ ucfirst($p->status) }}
                            </span>
                        </div>

                        <div class="md:col-span-2 flex items-center gap-2 justify-end">
                            {{-- Mark Paid --}}
                            @if($p->status !== 'paid')
                                <form id="mark-paid-{{ $p->id }}" method="POST" action="{{ route('admin.payouts.updateStatus', $p) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="paid">
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700"
                                            onclick="event.stopPropagation();">
                                        Mark paid
                                    </button>
                                </form>
                            @endif

                            {{-- Mark Failed --}}
                            @if($p->status !== 'failed')
                                <form id="mark-failed-{{ $p->id }}" method="POST" action="{{ route('admin.payouts.updateStatus', $p) }}" class="inline ms-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="failed">
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-rose-600 text-white hover:bg-rose-700"
                                            onclick="event.stopPropagation();">
                                        Mark failed
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-600">No payouts found.</div>
                @endforelse
            </div>

            <div class="p-4">
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
