{{-- resources/views/events/register-result.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Register for {{ $event->name }}
            </h2>
            <a href="{{ route('events.show', $event) }}"
               class="text-sm text-gray-600 hover:text-gray-800 underline">Back to event</a>
        </div>
    </x-slot>

    @php
        // Simple styles based on $state: success | error | warning | info
        $state = $state ?? 'info';
        $map = [
            'success' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'icon' => 'M9 12l2 2 4-4m6 2a10 10 0 11-20 0 10 10 0 0120 0z'],
            'error'   => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'text' => 'text-rose-800',    'icon' => 'M12 9v4m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z'],
            'warning' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-900',   'icon' => 'M12 9v4m0 4h.01M10.29 3.86l-7.5 13A1 1 0 003.62 19h16.76a1 1 0 00.87-1.5l-7.5-13a1 1 0 00-1.74 0z'],
            'info'    => ['bg' => 'bg-sky-50',     'border' => 'border-sky-200',     'text' => 'text-sky-900',     'icon' => 'M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z'],
        ];
        $sty = $map[$state] ?? $map['info'];

        $banner = $event->banner_url ? asset('storage/'.$event->banner_url) : null;
        $avatar = $event->avatar_url ? asset('storage/'.$event->avatar_url) : null;
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {{-- Event meta --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            @if($banner)
                <img src="{{ $banner }}" alt="{{ $event->name }}" class="h-36 w-full object-cover">
            @endif

            <div class="p-5">
                <div class="flex items-center gap-3">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="" class="h-10 w-10 rounded object-cover">
                    @endif
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ $event->name }}</div>
                        @if($event->location)
                            <div class="text-sm text-gray-500">{{ $event->location }}</div>
                        @endif
                    </div>
                </div>

                {{-- Result panel --}}
                <div class="mt-5 rounded-xl border {{ $sty['border'] }} {{ $sty['bg'] }} p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 {{ $sty['text'] }}" viewBox="0 0 24 24" fill="currentColor">
                            <path d="{{ $sty['icon'] }}"/>
                        </svg>
                        <div>
                            <div class="font-semibold {{ $sty['text'] }}">{{ $title ?? 'Registration' }}</div>
                            @if(!empty($message))
                                <p class="mt-1 text-sm {{ $sty['text'] }}">{{ $message }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Helpful next steps --}}
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ route('events.show', $event) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 text-gray-800 hover:bg-gray-200">
                        Back to event
                    </a>

                    {{-- If paid/registered, guide users to their tickets --}}
                    @if(($state ?? '') === 'success')
                        <a href="{{ route('my.tickets') }}"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            View my tickets
                        </a>
                    @endif
                </div>

                {{-- Optional: list upcoming session(s) for context --}}
                @if(($event->sessions ?? collect())->count())
                    <div class="mt-6">
                        <div class="text-sm font-medium text-gray-700 mb-2">Upcoming session(s)</div>
                        <ul class="space-y-1 text-sm text-gray-600">
                            @foreach($event->sessions as $s)
                                @php $dt = \Carbon\Carbon::parse($s->session_date); @endphp
                                <li>• {{ $s->session_name }} — {{ $dt->format('D, d M Y · g:ia') }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
