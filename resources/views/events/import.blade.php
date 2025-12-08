{{-- resources/views/events/import.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Import Event from URL
        </h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4">
        @if(session('error'))
            <div class="mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('import.handle') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Event URL
                </label>
                <input type="url" name="url" value="{{ old('url') }}"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="https://www.eventbrite.co.uk/e/..."
                       required>
                @error('url')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                Fetch details
            </button>
        </form>

        <p class="mt-4 text-sm text-gray-500">
            We’ll try to read the title, description and location from the page to speed up your event creation.
        </p>
    </div>
</x-app-layout>
