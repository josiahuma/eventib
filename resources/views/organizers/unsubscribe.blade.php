<x-app-layout>
<div class="max-w-3xl mx-auto px-4 py-12"><div class="form-card">
    <h1 class="text-3xl font-bold">{{ $disabled ? 'Email alerts are off.' : 'Turn off these event alerts?' }}</h1>
    <p class="my-6 text-gray-600">This only affects new-event emails from this organiser. Your tickets and registrations stay the same.</p>
    @unless($disabled)<form method="POST" action="{{ request()->fullUrl() }}">@csrf<button class="form-primary-btn">Turn off email alerts</button></form>@endunless
    <a href="{{ route('homepage') }}" class="inline-block mt-6">Back to Eventib →</a>
</div></div>
</x-app-layout>
