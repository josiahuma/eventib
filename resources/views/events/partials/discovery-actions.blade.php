@php
    $savedPlan = auth()->check() && \Illuminate\Support\Facades\DB::table('event_saves')->where('user_id', auth()->id())->where('event_id', $event->id)->exists();
@endphp
<div class="mb-6 flex flex-wrap items-center gap-3">
    @if(session('discovery_status'))
        <p role="status" class="w-full text-sm text-emerald-700">{{ session('discovery_status') }}</p>
    @endif
    @auth
        <form method="POST" action="{{ route('discovery.save', $event) }}">
            @csrf
            <input type="hidden" name="saved" value="{{ $savedPlan ? '0' : '1' }}">
            <button class="rounded-full border border-orange-300 bg-white px-5 py-3 text-sm font-semibold text-gray-900">{{ $savedPlan ? '♥ Saved · remove' : '♡ Save this plan' }}</button>
        </form>
    @else
        <a class="rounded-full border bg-white px-5 py-3 text-sm font-semibold" href="{{ route('login') }}">♡ Sign in to save</a>
    @endauth
    <a href="{{ route('discovery.share', $event) }}" class="rounded-full bg-orange-500 px-5 py-3 text-sm font-semibold text-white">Make a social invite ↗</a>
    <a href="{{ route('discovery.saved') }}" class="px-3 py-3 text-sm font-semibold">My Plans →</a>
</div>
