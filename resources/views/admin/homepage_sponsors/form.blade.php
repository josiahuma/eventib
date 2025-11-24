<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $mode === 'edit' ? 'Edit Homepage Sponsor' : 'Add Homepage Sponsor' }}
            </h2>
            <a href="{{ route('admin.homepage-sponsors.index') }}"
               class="text-sm text-gray-600 hover:text-gray-800 underline">
                Back to sponsors
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                <div class="font-semibold mb-1">Please fix the following:</div>
                <ul class="list-disc ml-5 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ $mode === 'edit'
                        ? route('admin.homepage-sponsors.update', $sponsor)
                        : route('admin.homepage-sponsors.store') }}"
            method="POST"
            enctype="multipart/form-data"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-6 space-y-6"
        >
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sponsor name</label>
                    <input type="text" name="name"
                           value="{{ old('name', $sponsor->name) }}"
                           required
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Website URL (optional)</label>
                    <input type="url" name="website_url"
                           value="{{ old('website_url', $sponsor->website_url) }}"
                           placeholder="https://example.com"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        From date (optional)
                    </label>
                    <input type="date" name="starts_on"
                           value="{{ old('starts_on', optional($sponsor->starts_on)->format('Y-m-d')) }}"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        To date (optional)
                    </label>
                    <input type="date" name="ends_on"
                           value="{{ old('ends_on', optional($sponsor->ends_on)->format('Y-m-d')) }}"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Priority
                    </label>
                    <input type="number" min="1" max="9999" name="priority"
                           value="{{ old('priority', $sponsor->priority ?? 10) }}"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 text-sm">
                    <p class="mt-1 text-xs text-gray-500">
                        Lower numbers show first when multiple campaigns are valid on the same day.
                    </p>
                </div>

                <div class="flex items-center mt-6 sm:mt-0">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           class="rounded border-gray-300 text-indigo-600"
                           {{ old('is_active', $sponsor->is_active ?? true) ? 'checked' : '' }}>
                    <label for="is_active" class="ml-2 text-sm text-gray-700">
                        Active
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Logo (square recommended)
                    </label>
                    <input type="file" name="logo" accept="image/*"
                           class="w-full rounded-lg border-gray-300 text-sm">

                    @if ($sponsor->logo_path)
                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                            <img src="{{ asset('storage/'.$sponsor->logo_path) }}"
                                 alt="{{ $sponsor->name }}"
                                 class="h-10 w-10 rounded-full object-contain bg-gray-100">
                            <span class="truncate">{{ $sponsor->logo_path }}</span>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Background image (homepage skin)
                    </label>
                    <input type="file" name="background" accept="image/*"
                           class="w-full rounded-lg border-gray-300 text-sm">

                    @if ($sponsor->background_path)
                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                            <img src="{{ asset('storage/'.$sponsor->background_path) }}"
                                 alt="{{ $sponsor->name }}"
                                 class="h-12 w-20 rounded object-cover bg-gray-100">
                            <span class="truncate">{{ $sponsor->background_path }}</span>
                        </div>
                    @endif

                    <p class="mt-1 text-xs text-gray-500">
                        This will be used on the sides of the homepage on desktop (like Eventim).
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('admin.homepage-sponsors.index') }}"
                   class="text-sm text-gray-600 hover:text-gray-800">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $mode === 'edit' ? 'Save changes' : 'Create sponsor' }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
