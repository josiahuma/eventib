{{-- resources/views/static/contact.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Contact us
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- Success message --}}
        @if (session('success'))
            <div class="mb-6 border border-emerald-200 bg-emerald-50 text-emerald-800 rounded-xl p-4">
                {{ session('success') }}
            </div>
        @endif

        {{-- Card --}}
        <div class="form-card">

            <p class="text-slate-600 mb-6">
                Have a question, feature request, or need help?  
                Send us a message and we’ll get back to you promptly.
            </p>

            <form method="POST" action="{{ route('contact.submit') }}" class="space-y-5">
                @csrf

                {{-- Honeypot --}}
                <input type="text" name="website" class="hidden" autocomplete="off">

                {{-- Name --}}
                <div>
                    <label class="form-label">Your name</label>
                    <input
                        type="text"
                        name="name"
                        required
                        value="{{ old('name') }}"
                        placeholder="Jane Doe"
                        class="form-input"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        required
                        value="{{ old('email') }}"
                        placeholder="you@example.com"
                        class="form-input"
                    >
                    @error('email')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Subject --}}
                <div>
                    <label class="form-label">Subject</label>
                    <input
                        type="text"
                        name="subject"
                        required
                        value="{{ old('subject') }}"
                        placeholder="How can we help?"
                        class="form-input"
                    >
                    @error('subject')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Message --}}
                <div>
                    <label class="form-label">Message</label>
                    <textarea
                        name="message"
                        rows="6"
                        required
                        placeholder="Give us a quick overview…"
                        class="form-input min-h-[140px]"
                    >{{ old('message') }}</textarea>

                    @error('message')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between pt-2">
                    <p class="form-help">
                        Or email us directly at
                        <a href="mailto:info@eventib.com" class="underline">info@eventib.com</a>
                    </p>

                    <button class="form-primary-btn">
                        Send message
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
