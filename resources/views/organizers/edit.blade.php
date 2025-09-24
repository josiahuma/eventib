<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-4">

        <h1 class="text-2xl font-bold mb-6">Edit Organizer</h1>

        <form action="{{ route('organizers.update', $organizer) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Name --}}
            <div>
                <label class="block font-medium mb-1" for="name">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $organizer->name) }}"
                       class="w-full border-gray-300 rounded-lg focus:ring-orange-500">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Bio --}}
            <div>
                <label class="block font-medium mb-1" for="bio">Bio</label>
                <textarea name="bio" id="bio" rows="4"
                          class="w-full border-gray-300 rounded-lg focus:ring-orange-500">{{ old('bio', $organizer->bio) }}</textarea>
                @error('bio') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Avatar --}}
            <div>
                <label class="block font-medium mb-1" for="avatar">Avatar</label>
                <input type="file" name="avatar" id="avatar" class="block w-full text-sm text-gray-500">
                @if($organizer->avatar_url)
                    <img src="{{ asset('storage/' . $organizer->avatar_url) }}" class="w-24 h-24 rounded-full mt-3 object-cover">
                @endif
                @error('avatar') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <div>
                <button type="submit"
                        class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-md shadow font-semibold">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
