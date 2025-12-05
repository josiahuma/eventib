<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Organiser Dashboard
            </h2>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {{-- Top buttons --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('events.create') }}"
               class="flex items-center justify-center px-4 py-3 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                Create Events
            </a>

            <a href="{{ route('events.manage') }}"
               class="flex items-center justify-center px-4 py-3 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-black">
                Manage Events
            </a>

            <a href="{{ route('dashboard.report', ['year' => $year, 'metric' => $metric]) }}"
               class="flex items-center justify-center px-4 py-3 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                Download Report (PDF)
            </a>

            {{-- Update this href when your scanner app link is ready --}}
            <a href="{{ route('organizerapp') }}"
               class="flex items-center justify-center px-4 py-3 rounded-xl bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900">
                Download Eventib Scanner App
            </a>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Year</label>
                    <select name="year"
                            class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Metric</label>
                    <select name="metric"
                            class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="attendees" @selected($metric === 'attendees')>Total registered attendees</option>
                        <option value="events" @selected($metric === 'events')>Number of events posted</option>
                        <option value="earnings" @selected($metric === 'earnings')>Total earnings</option>
                        <option value="checkins" @selected($metric === 'checkins')>Total check-ins</option>
                    </select>
                </div>

                <div class="pt-5">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                        Apply
                    </button>
                </div>
            </form>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">Events posted ({{ $year }})</div>
                <div class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($eventsCount) }}</div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">Total earnings ({{ $year }})</div>
                <div class="mt-2 text-2xl font-bold text-gray-900">
                    £{{ number_format($totalEarnings / 100, 2) /* if stored in pence; adjust if not */ }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">Registered attendees ({{ $year }})</div>
                <div class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($totalAttendees) }}</div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">Total check-ins ({{ $year }})</div>
                <div class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($totalCheckins) }}</div>
            </div>
        </div>

        {{-- Bar chart --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">
                    @switch($metric)
                        @case('events') Events per month @break
                        @case('earnings') Earnings per month @break
                        @case('checkins') Check-ins per month @break
                        @default Registered attendees per month
                    @endswitch
                    – {{ $year }}
                </h3>
            </div>

            {{-- Fixed-height container so Chart.js can’t grow infinitely --}}
            <div class="relative h-64">
                <canvas id="kpiChart" class="absolute inset-0 w-full h-full"></canvas>
            </div>
        </div>

    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('kpiChart').getContext('2d');
            const data = @json($chartData);
            const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: '{{ ucfirst($metric) }}',
                        data: data,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });
    </script>
</x-app-layout>
