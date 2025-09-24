@component('mail::message')
# Payout requested

**Payout ID:** {{ $payout->id }}  
**User:** {{ optional($payout->user)->email }}  
**Amount:** {{ $payout->currency }} {{ number_format($payout->amount_minor/100, 2) }}  
**Status:** {{ $payout->status }}

@component('mail::button', ['url' => config('app.url').'/admin/payouts/'.$payout->id])
Review payout
@endcomponent
@endcomponent
