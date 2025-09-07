<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Homepage Slides</h2>
            <a href="{{ route('admin.slides.create') }}" class="px-3 py-2 rounded-lg bg-indigo-600 text-white">+ New slide</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">{{ session('success') }}</div>
        @endif

        @if ($slides->count())
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                @foreach ($slides as $s)
                    <div class="bg-white border rounded-xl p-4 flex gap-3 items-start">
                        <img src="{{ asset('storage/'.$s->image_path) }}" class="h-24 w-40 object-cover rounded">
                        <div class="flex-1">
                            <div class="font-medium">{{ $s->title ?: 'Untitled' }}</div>
                            <div class="text-sm text-gray-600">Sort: {{ $s->sort }} · {{ $s->is_active ? 'Active' : 'Inactive' }}</div>
                            @if($s->link_url)
                                <div class="text-sm text-gray-600 truncate">Link: <a href="{{ $s->link_url }}" target="_blank" class="underline">{{ $s->link_url }}</a></div>
                            @endif
                            <div class="text-xs text-gray-500">
                                {{ $s->starts_at ? $s->starts_at->toDayDateTimeString() : '—' }} → {{ $s->ends_at ? $s->ends_at->toDayDateTimeString() : '—' }}
                            </div>
                            <div class="mt-3 flex gap-2">
                                <a href="{{ route('admin.slides.edit', $s) }}" class="px-3 py-1.5 text-sm rounded bg-gray-100">Edit</a>
                                <form method="POST" action="{{ route('admin.slides.destroy', $s) }}" onsubmit="return confirm('Delete this slide?')">
                                    @csrf @method('DELETE')
                                    <button class="px-3 py-1.5 text-sm rounded bg-rose-600 text-white">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white border rounded-xl p-8 text-center">
                <div class="font-medium">No slides yet</div>
                <p class="text-sm text-gray-500">Create one to start rotating banners on the homepage.</p>
            </div>
        @endif
    </div>
</x-app-layout>
