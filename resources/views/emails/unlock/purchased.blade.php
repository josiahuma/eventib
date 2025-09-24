@component('mail::message')
# Your Event Unlock is active 🎉

Hi {{ $userName }},  
Your unlock payment of **{{ $currency }} {{ $amount }}** was successful.

@isset($receiptUrl)
@component('mail::button', ['url' => $receiptUrl])
View Receipt
@endcomponent
@endisset

You can now view and manage your free-event registrants.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
