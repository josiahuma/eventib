<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $slide->exists ? 'Edit Slide' : 'New Slide' }}
            </h2>
            <a href="{{ route('admin.slides.index') }}" class="text-sm underline text-gray-600">Back</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $slide->exists ? route('admin.slides.update', $slide) : route('admin.slides.store') }}"
              class="bg-white border rounded-2xl p-6 shadow-sm">
            @csrf
            @if($slide->exists) @method('PUT') @endif

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                    <ul class="list-disc ms-5 text-sm">
                        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Title (optional)</label>
                    <input name="title" value="{{ old('title', $slide->title) }}" class="mt-1 w-full rounded-lg border-gray-300">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Image {{ $slide->exists ? '(replace)' : '' }}</label>
                    <input type="file" name="image" accept="image/*" {{ $slide->exists ? '' : 'required' }} class="mt-1 w-full rounded-lg border-gray-300">
                    @if ($slide->image_path)
                        <img src="{{ asset('storage/'.$slide->image_path) }}" class="mt-2 h-24 rounded object-cover">
                    @endif
                    <p class="text-xs text-gray-500 mt-1">Large, wide image recommended (e.g., 1600×600).</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Click-through URL (optional)</label>
                    <input type="url" name="link_url" value="{{ old('link_url', $slide->link_url) }}" class="mt-1 w-full rounded-lg border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Sort</label>
                    <input type="number" name="sort" value="{{ old('sort', $slide->sort ?? 0) }}" class="mt-1 w-full rounded-lg border-gray-300">
                </div>

                <div class="flex items-center gap-2 mt-6">
                    <input type="checkbox" name="is_active" value="1" id="active" class="rounded border-gray-300"
                           @checked(old('is_active', $slide->is_active))>
                    <label for="active" class="text-sm text-gray-700">Active</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Start (optional)</label>
                    <input type="datetime-local" name="starts_at"
                           value="{{ old('starts_at', $slide->starts_at ? $slide->starts_at->format('Y-m-d\TH:i') : '') }}"
                           class="mt-1 w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">End (optional)</label>
                    <input type="datetime-local" name="ends_at"
                           value="{{ old('ends_at', $slide->ends_at ? $slide->ends_at->format('Y-m-d\TH:i') : '') }}"
                           class="mt-1 w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.slides.index') }}" class="px-4 py-2 rounded border">Cancel</a>
                <button class="px-4 py-2 rounded bg-indigo-600 text-white">{{ $slide->exists ? 'Save' : 'Create' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
