{{-- resources/views/static/about.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">About Eventib</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
        {{-- Hero --}}
        <section class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm">
            <div class="grid md:grid-cols-2 gap-8 items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Simple, modern ticketing for every event</h1>
                    <p class="mt-3 text-gray-600">
                        Eventib helps you create, promote and sell tickets in minutes. No clunky dashboards, no surprises—
                        just a clean flow for organisers and a delightful checkout for attendees.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('events.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                            Create your event
                        </a>
                        <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg border px-4 py-2 text-gray-700 hover:bg-gray-50">
                            See pricing
                        </a>
                    </div>
                </div>
                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-emerald-50 p-6">
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <li class="rounded-lg border bg-white p-4">
                            <div class="text-sm text-gray-500">Checkout</div>
                            <div class="text-lg font-semibold text-gray-900">Fast & secure</div>
                        </li>
                        <li class="rounded-lg border bg-white p-4">
                            <div class="text-sm text-gray-500">Payouts</div>
                            <div class="text-lg font-semibold text-gray-900">Multiple currencies</div>
                        </li>
                        <li class="rounded-lg border bg-white p-4">
                            <div class="text-sm text-gray-500">Fees</div>
                            <div class="text-lg font-semibold text-gray-900">5.9% platform fee</div>
                        </li>
                        <li class="rounded-lg border bg-white p-4">
                            <div class="text-sm text-gray-500">Scanning</div>
                            <div class="text-lg font-semibold text-gray-900">Built-in QR check-in</div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Feature grid --}}
        <section class="grid md:grid-cols-3 gap-6">
            @php
                $features = [
                    ['title'=>'Create events fast','body'=>'Three-step flow: pricing & payout, basics, schedule & media.'],
                    ['title'=>'Flexible ticket types','body'=>'Standard, VIP, early bird, capacities, sorting and active/inactive states.'],
                    ['title'=>'Pass or absorb fees','body'=>'Let attendees pay the 5.9% fee or absorb it—your choice at creation.'],
                    ['title'=>'Multi-session support','body'=>'Add sessions with titles, dates and start times.'],
                    ['title'=>'Built-in scanning','body'=>'Mobile-friendly QR scanner with instant check-in.'],
                    ['title'=>'Payouts you control','body'=>'Request payouts anytime; track processing and history.'],
                ];
            @endphp
            @foreach ($features as $f)
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                    <div class="text-lg font-semibold text-gray-900">{{ $f['title'] }}</div>
                    <p class="mt-2 text-sm text-gray-600">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </section>

        {{-- CTA --}}
        <section class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl p-8 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="text-xl font-semibold">Ready to host with Eventib?</div>
                <div class="flex gap-3">
                    <a href="{{ route('events.create') }}" class="rounded-lg bg-white/10 px-4 py-2 hover:bg-white/20">Create an event</a>
                    <a href="{{ route('contact') }}" class="rounded-lg bg-black/20 px-4 py-2 hover:bg-black/30">Contact us</a>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>