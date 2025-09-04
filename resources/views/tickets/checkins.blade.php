{{-- resources/views/tickets/checkins.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Check-ins — {{ $event->name }}
            </h2>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('tickets.scan', $event) }}" class="underline text-gray-700">Scan tickets</a>
                <a href="{{ route('dashboard') }}" class="underline text-gray-600">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- KPIs --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Total {{ $mode==='paid' ? 'tickets' : 'registrations' }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($total) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Checked-in</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($checked) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Attendance checked-in</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($attendanceCheckedIn) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Attendance</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $percent }}%</div>
            </div>
        </div>


        {{-- Filters --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm mb-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input type="hidden" name="page" value="1" />

                <div>
                    <label class="block text-sm text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border-gray-300" onchange="this.form.submit()">
                        <option value="checked" {{ $status==='checked' ? 'selected' : '' }}>Checked-in only</option>
                        <option value="all"     {{ $status==='all'     ? 'selected' : '' }}>All</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-600 mb-1">Session</label>
                    <select name="session" class="w-full rounded-lg border-gray-300" onchange="this.form.submit()">
                        <option value="">All sessions</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ (string)$sid===(string)$s->id ? 'selected':'' }}>
                                {{ $s->session_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600 mb-1">Search</label>
                    <div class="flex">
                        <input type="text" name="q" value="{{ $term }}" placeholder="Name, email{{ $mode==='paid' ? ', serial' : '' }}"
                               class="w-full rounded-l-lg border-gray-300" />
                        <button class="px-3 rounded-r-lg border border-l-0 bg-gray-50">Go</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b text-sm text-gray-600">
                {{ $items->total() }} {{ Str::plural($mode==='paid' ? 'ticket' : 'registration', $items->total()) }}
                @if($status==='checked') <span class="text-gray-400">· checked-in</span>@endif
                @if($sid) <span class="text-gray-400">· filtered by session</span>@endif
                @if($term) <span class="text-gray-400">· “{{ $term }}”</span>@endif
            </div>

            <div class="divide-y">
                @forelse($items as $row)
                    @if($mode==='paid')
                        @php
                            $reg = $row->registration;
                            $sess = $reg?->sessions?->pluck('session_name')->join(', ') ?? '—';
                        @endphp
                        <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                            <div class="md:col-span-3">
                                <div class="text-sm text-gray-500">Serial</div>
                                <div class="font-medium text-gray-900">{{ $row->serial }}</div>
                            </div>
                            <div class="md:col-span-4 min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $reg?->name ?? '—' }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $reg?->email ?? '—' }}</div>
                                <div class="text-xs text-gray-500 mt-1">Sessions: {{ $sess }}</div>
                            </div>
                            <div class="md:col-span-3">
                                <div class="text-sm text-gray-500">Checked-in</div>
                                <div class="font-medium text-gray-900">
                                    {{ $row->checked_in_at?->format('d M Y, g:ia') ?? '—' }}
                                </div>
                                @if($row->checkedInBy)
                                    <div class="text-xs text-gray-500">by {{ $row->checkedInBy->name }}</div>
                                @endif
                            </div>
                            <div class="md:col-span-2 text-right">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs
                                    {{ $row->checked_in_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $row->checked_in_at ? 'Checked-in' : 'Not checked-in' }}
                                </span>
                            </div>
                        </div>
                    @else
                        @php
                            $ad = (int)($row->party_adults ?? 0);
                            $ch = (int)($row->party_children ?? 0);
                            $party = 1 + $ad + $ch;
                            $sess = $row?->sessions?->pluck('session_name')->join(', ') ?? '—';
                        @endphp
                        <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                            <div class="md:col-span-4 min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $row->name ?? '—' }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $row->email ?? '—' }}</div>
                                <div class="text-xs text-gray-500 mt-1">Sessions: {{ $sess }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-sm text-gray-500">Party</div>
                                <div class="font-medium text-gray-900">{{ $party }}</div>
                                @if($ad+$ch>0)
                                    <div class="text-xs text-gray-500">{{ $ad }} adult{{ $ad===1?'':'s' }}, {{ $ch }} child{{ $ch===1?'':'ren' }}</div>
                                @endif
                            </div>
                            <div class="md:col-span-4">
                                <div class="text-sm text-gray-500">Checked-in</div>
                                <div class="font-medium text-gray-900">
                                    {{ $row->checked_in_at?->format('d M Y, g:ia') ?? '—' }}
                                </div>
                                @if($row->checkedInBy)
                                    <div class="text-xs text-gray-500">by {{ $row->checkedInBy->name }}</div>
                                @endif
                            </div>
                            <div class="md:col-span-2 text-right">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs
                                    {{ $row->checked_in_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $row->checked_in_at ? 'Checked-in' : 'Not checked-in' }}
                                </span>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="p-8 text-center text-gray-600">No records found.</div>
                @endforelse
            </div>

            <div class="p-4">
                {{ $items->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
