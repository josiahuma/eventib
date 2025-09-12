{{-- resources/views/static/contact.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contact us</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if (session('success'))
            <div class="mb-6 p-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
            <p class="text-gray-600 mb-6">
                Have a question, feature request, or need help? Send us a message and we’ll respond promptly.
            </p>

            <form method="POST" action="{{ route('contact.submit') }}" class="grid grid-cols-1 gap-4">
                @csrf
                {{-- honeypot --}}
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-gray-300" placeholder="Jane Doe" value="{{ old('name') }}">
                    @error('name') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-gray-300" placeholder="you@example.com" value="{{ old('email') }}">
                    @error('email') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" required class="w-full rounded-lg border-gray-300" placeholder="How can we help?" value="{{ old('subject') }}">
                    @error('subject') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea name="message" rows="6" required class="w-full rounded-lg border-gray-300" placeholder="Give us a quick overview…">{{ old('message') }}</textarea>
                    @error('message') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Or email us directly at <a class="underline" href="mailto:info@eventib.com">info@eventib.com</a></p>
                    <button class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        Send message
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>