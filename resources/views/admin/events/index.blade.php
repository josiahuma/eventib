{{-- resources/views/admin/events/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage events</h2>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Admin home</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">{{ session('success') }}</div>
        @endif

        <form method="GET" class="mb-4">
            <input name="q" value="{{ $q }}" class="w-full sm:w-96 rounded-lg border-gray-300" placeholder="Search event name or category">
        </form>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b text-sm text-gray-600">
                {{ $events->total() }} {{ Str::plural('event', $events->total()) }}
            </div>

            <div class="divide-y">
                @forelse($events as $e)
                    @php
                        $isFree = ($e->ticket_cost ?? 0) == 0;
                        $cur = strtoupper($e->ticket_currency ?? 'GBP');
                        $symbols = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
                        $sym = $symbols[$cur] ?? '';
                        $priceLabel = $isFree ? 'Free' : ($sym ? $sym.number_format($e->ticket_cost,2) : $cur.' '.number_format($e->ticket_cost,2));
                    @endphp
                    <div class="p-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 truncate">{{ $e->name }}</div>
                            <div class="text-sm text-gray-600">
                                By {{ $e->user->name ?? '—' }} · {{ $e->category ?? 'Uncategorised' }} · {{ $priceLabel }}
                            </div>
                            <div class="mt-1 flex gap-2">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $e->is_promoted ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $e->is_promoted ? 'Promoted' : 'Not promoted' }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $e->is_disabled ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $e->is_disabled ? 'Disabled' : 'Active' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.events.toggle-promote', $e) }}">
                                @csrf @method('PATCH')
                                <button class="px-3 py-1.5 text-sm rounded-md border bg-white hover:bg-gray-50">
                                    {{ $e->is_promoted ? 'Unpromote' : 'Promote' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.events.toggle-disabled', $e) }}">
                                @csrf @method('PATCH')
                                <button class="px-3 py-1.5 text-sm rounded-md {{ $e->is_disabled ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-rose-600 text-white hover:bg-rose-700' }}">
                                    {{ $e->is_disabled ? 'Enable' : 'Disable' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-600">No events found.</div>
                @endforelse
            </div>

            <div class="p-4">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
