@php
    $pickerConfig = $booking;
    $pickerConfig['sessionIds'] = $booking['sessions'] ? [(string)$booking['sessions'][0]['id']] : [];
@endphp
@if($hasUpcoming)
<form method="GET" action="{{ route('events.register', $event) }}" x-data="eventibTicketPicker(@js($pickerConfig))" class="mt-5 space-y-5">
    <fieldset><legend class="form-label">Choose your session(s)</legend>
    <div class="space-y-2 max-h-56 overflow-y-auto">
        @foreach($booking['sessions'] as $session)
        <label class="flex items-start gap-2 border rounded-xl px-3 py-3">
            <input type="checkbox" name="session_ids[]" value="{{ $session['id'] }}" x-model="sessionIds" class="form-checkbox mt-1">
            <span class="text-sm"><strong>{{ $session['name'] }}</strong><br><span class="text-gray-600">{{ $session['date'] }}</span></span>
        </label>@endforeach
    </div></fieldset>
    @if($booking['mode'] === 'cats')
    <fieldset><legend class="form-label">Choose tickets</legend>
        @foreach($booking['categories'] as $category)
        <div class="flex items-center justify-between gap-3 py-3 border-b">
            <label for="pick-category-{{ $category['id'] }}" class="text-sm"><strong>{{ $category['name'] }}</strong><br>{{ $booking['symbol'] }}{{ number_format($category['priceMinor']/100, 2) }}</label>
            <input id="pick-category-{{ $category['id'] }}" type="number" name="categories[{{ $category['id'] }}]" x-model.number="quantities[{{ $category['id'] }}]" min="0" max="100" step="1" class="form-input" style="width:80px">
        </div>@endforeach
    </fieldset>
    @elseif($booking['mode'] === 'single')
    <div><label class="form-label" for="pick-quantity">Tickets</label><input id="pick-quantity" type="number" name="quantity" x-model.number="quantity" min="1" max="10" step="1" class="form-input"></div>
    @else
    <fieldset><legend class="form-label">Bring your people</legend><p class="form-help mb-3">Your place is included. Add companions below.</p>
        <div class="grid grid-cols-2 gap-3"><div><label for="pick-adults" class="form-label">Extra adults</label><input id="pick-adults" type="number" name="party_adults" x-model.number="adults" min="0" max="20" class="form-input"></div><div><label for="pick-children" class="form-label">Children</label><input id="pick-children" type="number" name="party_children" x-model.number="children" min="0" max="20" class="form-input"></div></div>
        @include('events.partials.child-ages')
    </fieldset>
    @endif
    <div class="rounded-xl bg-gray-50 p-4 text-sm" aria-live="polite">
        <div class="flex justify-between"><span>Ticket subtotal</span><strong x-text="money(subtotalMinor())"></strong></div>
        <div class="flex justify-between mt-2"><span>Booking fee</span><span x-text="money(feeMinor())"></span></div>
        <div class="flex justify-between mt-3 pt-3 border-t text-base"><strong>Estimated total</strong><strong x-text="money(totalMinor())"></strong></div>
        <p class="form-help mt-3">Your selection applies to all chosen sessions. This does not reserve tickets. Review your choices before confirming.</p>
    </div>
    <button class="form-primary-btn w-full justify-center disabled:opacity-50" :disabled="!canContinue()">Continue to registration →</button>
    <noscript><p class="text-sm">JavaScript is needed for this picker. <a href="{{ route('events.register', $event) }}" class="underline">Open registration directly.</a></p></noscript>
</form>
@else <p class="mt-5 rounded-xl bg-gray-100 p-4 text-gray-600 text-center">Registration closed</p>
@endif
