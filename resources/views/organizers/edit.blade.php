{{-- resources/views/organizers/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit organizer
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Error summary --}}
        @if ($errors->any())
            <div class="mb-6 border border-rose-200 bg-rose-50 text-rose-800 rounded-xl p-4">
                <div class="font-semibold mb-1">Please fix the following:</div>
                <ul class="list-disc ms-5 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-card">
            <h3 class="form-section-title">Organizer details</h3>

            <form action="{{ route('organizers.update', $organizer) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="mt-4 space-y-5">
                @csrf
                @method('PUT')

                {{-- Organizer name --}}
                <div>
                    <label class="form-label">Organizer name</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $organizer->name) }}"
                        required
                        class="form-input"
                        placeholder="e.g., Eventib Productions"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Bio --}}
                <div>
                    <label class="form-label">Bio</label>
                    <textarea
                        name="bio"
                        rows="4"
                        class="form-input resize-y"
                        placeholder="Tell attendees a bit about this organiser…"
                    >{{ old('bio', $organizer->bio) }}</textarea>
                    @error('bio')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Avatar --}}
                <div>
                    <label class="form-label">Avatar / Logo (optional)</label>

                    <label class="block">
                        <span class="inline-flex items-center justify-center rounded-md border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-700 cursor-pointer hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H4z"/>
                            </svg>
                            <span>Choose image</span>
                        </span>

                        <input
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="hidden"
                        >
                    </label>

                    @if($organizer->avatar_url)
                        <div class="mt-3 flex items-center gap-3">
                            <img src="{{ asset('storage/'.$organizer->avatar_url) }}"
                                 class="h-16 w-16 rounded object-cover border">
                            <span class="text-xs text-gray-600 truncate">{{ $organizer->avatar_url }}</span>
                        </div>
                    @endif

                    <p class="form-help">Square image recommended (e.g. 400×400px).</p>

                    @error('avatar')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Website --}}
                <div>
                    <label class="form-label">Website</label>
                    <input
                        type="text"
                        name="website"
                        value="{{ old('website', $organizer->website) }}"
                        class="form-input"
                        placeholder="https://example.com"
                    >
                    @error('website')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4 flex items-center justify-end">
                    <button type="submit" class="form-primary-btn">
                        Save changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
