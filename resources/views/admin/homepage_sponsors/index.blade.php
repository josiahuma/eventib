<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Homepage Sponsors
            </h2>
            <a href="{{ route('admin.homepage-sponsors.create') }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">
                + Add Sponsor
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Active</th>
                        <th class="px-4 py-2 text-left">Dates</th>
                        <th class="px-4 py-2 text-left">Priority</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($sponsors as $sponsor)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($sponsor->logo_path)
                                        <img src="{{ asset('storage/'.$sponsor->logo_path) }}"
                                             alt="{{ $sponsor->name }}" class="h-8 w-8 rounded-full object-contain bg-gray-100">
                                    @endif
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ $sponsor->name }}
                                        </div>
                                        @if ($sponsor->website_url)
                                            <a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                                {{ $sponsor->website_url }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($sponsor->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @if ($sponsor->starts_on || $sponsor->ends_on)
                                    {{ $sponsor->starts_on?->format('d M Y') ?? '—' }}
                                    &mdash;
                                    {{ $sponsor->ends_on?->format('d M Y') ?? '—' }}
                                @else
                                    <span class="text-gray-400">Always on</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $sponsor->priority }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.homepage-sponsors.edit', $sponsor) }}"
                                   class="text-xs font-medium text-indigo-600 hover:text-indigo-800 mr-3">
                                    Edit
                                </a>

                                <form action="{{ route('admin.homepage-sponsors.destroy', $sponsor) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Delete this sponsor?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-medium text-rose-600 hover:text-rose-800">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                No homepage sponsors yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sponsors->links('pagination::tailwind') }}
        </div>
    </div>
</x-app-layout>
