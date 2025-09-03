{{-- resources/views/admin/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin dashboard</h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back to user dashboard</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Quick stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Users</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['users_total']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Disabled: {{ number_format($stats['users_disabled']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Events</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['events_total']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Disabled: {{ number_format($stats['events_disabled']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Payouts</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['payouts_total']) }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    Paid: {{ number_format($stats['payouts_paid']) }} • Processing: {{ number_format($stats['payouts_processing']) }}
                </div>
            </div>
        </div>

        {{-- Tiles --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.payouts.index') }}"
               class="group bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    {{-- icon --}}
                    <svg class="h-8 w-8 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 6.75A2.25 2.25 0 016.75 4.5h10.5A2.25 2.25 0 0119.5 6.75v10.5A2.25 2.25 0 0117.25 19.5H6.75A2.25 2.25 0 014.5 17.25V6.75zM7.5 8.25a.75.75 0 000 1.5h9a.75.75 0 000-1.5h-9zM7.5 12a.75.75 0 000 1.5h5.25a.75.75 0 000-1.5H7.5z"/></svg>
                    <div>
                        <div class="text-lg font-semibold">Manage payouts</div>
                        <div class="text-sm text-gray-500">Approve / mark paid / fail</div>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.users.index') }}"
               class="group bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 text-indigo-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm-9 9a9 9 0 1118 0H3z"/></svg>
                    <div>
                        <div class="text-lg font-semibold">Manage users</div>
                        <div class="text-sm text-gray-500">Promote to admin / disable</div>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.events.index') }}"
               class="group bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 text-rose-600" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2a1 1 0 011 1v1h10V3a1 1 0 112 0v1h1.5a1.5 1.5 0 011.5 1.5V9H2V5.5A1.5 1.5 0 013.5 4H5V3a1 1 0 011-1z"/><path d="M2 10h20v8.5A1.5 1.5 0 0120.5 20H3.5A1.5 1.5 0 012 18.5V10z"/></svg>
                    <div>
                        <div class="text-lg font-semibold">Manage events</div>
                        <div class="text-sm text-gray-500">Disable / promote events</div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>
