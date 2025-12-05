<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                Eventib Scanner App
            </h1>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12">
        {{-- Hero --}}
        <section class="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">
                    Check-in your attendees in seconds.
                </h2>
                <p class="mt-4 text-base text-gray-600">
                    Eventib Scanner is the companion mobile app for organisers.
                    Scan QR tickets, verify digital passes and see live check-in stats
                    at the door – all synced with your Eventib dashboard.
                </p>

                <div class="mt-6 space-y-3">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">
                        Coming soon to:
                    </p>
                    <div class="flex flex-wrap gap-3">
                        {{-- Store badges – you can replace hrefs when apps go live --}}
                        <a href="#"
                           class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-2.5 bg-white shadow-sm">
                            <span class="text-2xs uppercase tracking-wide text-gray-500">Coming soon on</span>
                            <span class="text-sm font-semibold text-gray-900">App Store</span>
                        </a>

                        <a href="#"
                           class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-2.5 bg-white shadow-sm">
                            <span class="text-2xs uppercase tracking-wide text-gray-500">Coming soon on</span>
                            <span class="text-sm font-semibold text-gray-900">Google Play</span>
                        </a>
                    </div>

                    <p class="text-xs text-gray-500">
                        You’ll need an Eventib organiser account to sign in and use the scanner.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                            Go to organiser dashboard
                        </a>
                        <a href="{{ route('pricing') }}"
                           class="inline-flex items-center px-4 py-2.5 rounded-lg bg-gray-100 text-gray-800 text-sm font-semibold hover:bg-gray-200">
                            Learn more about Eventib
                        </a>
                    </div>
                </div>
            </div>

            {{-- Simple mock preview card --}}
            <div class="flex justify-center lg:justify-end">
                <div class="w-full max-w-xs rounded-3xl border border-gray-200 bg-white shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-br from-indigo-600 to-purple-600 px-4 py-3 text-white">
                        <div class="text-xs uppercase tracking-wide text-white/80">Eventib Scanner</div>
                        <div class="mt-1 text-sm font-semibold">Live check-in</div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs text-gray-500">Current event</div>
                                <div class="text-sm font-semibold text-gray-900">Tonight at The Hall</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Checked in</div>
                                <div class="text-lg font-bold text-emerald-600">128</div>
                            </div>
                        </div>

                        <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center">
                            <p class="text-xs text-gray-500 mb-2">Point camera at QR code</p>
                            <p class="text-sm font-medium text-gray-700">Ready to scan</p>
                        </div>

                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Supports QR tickets</span>
                            <span>Digital pass ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section>
            <h3 class="text-lg font-semibold text-gray-900">Why organisers love Eventib Scanner</h3>
            <dl class="mt-4 grid gap-6 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <dt class="text-sm font-semibold text-gray-900">Fast QR scanning</dt>
                    <dd class="mt-2 text-sm text-gray-600">
                        Scan Eventib tickets at the door with instant validation and clear
                        success / failure feedback.
                    </dd>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <dt class="text-sm font-semibold text-gray-900">Real-time sync</dt>
                    <dd class="mt-2 text-sm text-gray-600">
                        Check-in data syncs back to your Eventib dashboard so you can see live
                        attendance numbers as guests arrive.
                    </dd>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <dt class="text-sm font-semibold text-gray-900">Multi-device ready</dt>
                    <dd class="mt-2 text-sm text-gray-600">
                        Use multiple phones at different entrances – we’ll keep them all in sync.
                    </dd>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <dt class="text-sm font-semibold text-gray-900">Works with digital pass</dt>
                    <dd class="mt-2 text-sm text-gray-600">
                        Designed to work alongside Eventib’s digital pass check-in (voice / face) for
                        enhanced security.
                    </dd>
                </div>
            </dl>
        </section>

        {{-- How it works --}}
        <section>
            <h3 class="text-lg font-semibold text-gray-900">How it works on event day</h3>
            <ol class="mt-4 space-y-3 text-sm text-gray-700 list-decimal list-inside">
                <li>Download the Eventib Scanner app on your iOS or Android device.</li>
                <li>Sign in with the same organiser account you use on eventib.com.</li>
                <li>Select your event from the list and tap <strong>Start scanning</strong>.</li>
                <li>Scan each guest’s QR ticket or digital pass as they arrive.</li>
                <li>Track total check-ins live from your organiser dashboard.</li>
            </ol>
        </section>

        {{-- Footer note --}}
        <section class="border-t border-gray-100 pt-6">
            <p class="text-xs text-gray-500">
                Eventib Scanner is for organisers only. Attendees can simply show the ticket QR
                they received by email – no app required.
            </p>
        </section>
    </div>
</x-app-layout>
