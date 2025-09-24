@php
    $bgColors = [
        ['bg-indigo-100', 'text-indigo-700'],
        ['bg-pink-100', 'text-pink-700'],
        ['bg-green-100', 'text-green-700'],
        ['bg-yellow-100', 'text-yellow-700'],
        ['bg-purple-100', 'text-purple-700'],
        ['bg-red-100', 'text-red-700'],
        ['bg-blue-100', 'text-blue-700'],
        ['bg-teal-100', 'text-teal-700'],
    ];

    $id = $model->id ?? rand(1, 9999);
    $colorSet = $bgColors[$id % count($bgColors)];

    // Auto text size based on size class
    if (Str::contains($size, 'w-24') || Str::contains($size, 'h-24')) {
        $textSize = 'text-5xl';
    } elseif (Str::contains($size, 'w-16') || Str::contains($size, 'h-16')) {
        $textSize = 'text-3xl';
    } elseif (Str::contains($size, 'w-12') || Str::contains($size, 'h-12')) {
        $textSize = 'text-xl';
    } else {
        $textSize = 'text-sm';
    }
@endphp

<div class="{{ $size }} rounded-full flex items-center justify-center overflow-hidden {{ $colorSet[0] }}">
    @if (!empty($model->avatar_url))
        <img src="{{ asset('storage/' . $model->avatar_url) }}"
             alt="{{ $model->name ?? 'Avatar' }}"
             class="w-full h-full object-cover">
    @else
        <span class="font-bold {{ $colorSet[1] }} {{ $textSize }}">
            {{ strtoupper(substr($model->name ?? 'U', 0, 1)) }}
        </span>
    @endif
</div>
