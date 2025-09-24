@component('mail::message')
# Unlock purchased

**User:** {{ $userEmail }}  
**Amount:** {{ $currency }} {{ $amount }}  
**Stripe Session:** {{ $sessionId }}

@component('mail::button', ['url' => config('app.url').'/admin'])
Admin Dashboard
@endcomponent
@endcomponent
