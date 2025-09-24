@if (session('error'))
    <div class="mb-4 p-3 rounded bg-red-100 text-red-700 border border-red-200">
        {{ session('error') }}
    </div>
@endif

<x-app-layout>
    <div class="max-w-2xl mx-auto py-10">
        <h1 class="text-2xl font-bold mb-6">Create Organizer</h1>

        <form method="POST" action="{{ route('organizers.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium">Organizer Name</label>
                <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium">Bio</label>
                <textarea name="bio" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Avatar / Logo (optional)</label>
                <input type="file" name="avatar" accept="image/*" class="mt-1 block w-full" />
            </div>

            <div>
                <label class="block text-sm font-medium">Website</label>
                <input type="text" name="website" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                @error('website')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Create Organizer
            </button>
        </form>
    </div>
</x-app-layout>
