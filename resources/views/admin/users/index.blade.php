{{-- resources/views/admin/users/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage users</h2>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Admin home</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200">{{ session('error') }}</div>
        @endif

        <form method="GET" class="mb-4">
            <input name="q" value="{{ $q }}" class="w-full sm:w-80 rounded-lg border-gray-300" placeholder="Search name or email">
        </form>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b text-sm text-gray-600">
                {{ $users->total() }} {{ Str::plural('user', $users->total()) }}
            </div>

            <div class="divide-y">
                @forelse($users as $u)
                    <div class="p-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 truncate">{{ $u->name ?? '—' }}</div>
                            <div class="text-sm text-gray-600">{{ $u->email }}</div>
                            <div class="mt-1 flex gap-2">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $u->is_admin ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $u->is_admin ? 'Admin' : 'User' }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $u->is_disabled ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $u->is_disabled ? 'Disabled' : 'Active' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.users.toggle-admin', $u) }}">
                                @csrf @method('PATCH')
                                <button class="px-3 py-1.5 text-sm rounded-md border bg-white hover:bg-gray-50">
                                    {{ $u->is_admin ? 'Remove admin' : 'Make admin' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.toggle-disabled', $u) }}">
                                @csrf @method('PATCH')
                                <button class="px-3 py-1.5 text-sm rounded-md {{ $u->is_disabled ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-rose-600 text-white hover:bg-rose-700' }}">
                                    {{ $u->is_disabled ? 'Enable' : 'Disable' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-600">No users found.</div>
                @endforelse
            </div>

            <div class="p-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
