{{-- resources/views/events/import.blade.php --}}
<x-app-layout>
    @section('meta')
        <meta name="robots" content="noindex, nofollow">
    @endsection

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Import Event from URL
        </h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4">

        {{-- Error --}}
        @if(session('error'))
            <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Card --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6">

            <h3 class="text-lg font-semibold text-slate-900 mb-4">
                Enter an event URL
            </h3>

            <form method="POST" action="{{ route('import.handle') }}" class="space-y-5">
                @csrf

                {{-- Event URL --}}
                <div>
                    <label class="form-label">Event URL</label>

                    <input
                        type="url"
                        name="url"
                        value="{{ old('url') }}"
                        required
                        placeholder="https://www.eventbrite.co.uk/e/..."
                        class="form-input"
                    >

                    @error('url')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="form-primary-btn">
                    Fetch details
                </button>
            </form>

            <p class="form-help mt-5">
                We’ll try to read the title, description and location from the page to
                speed up your event creation.
            </p>
        </div>
    </div>
</x-app-layout>
