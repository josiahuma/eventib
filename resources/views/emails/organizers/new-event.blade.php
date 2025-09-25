@component('mail::message')
# {{ $organizer->name }} just created a new event 🎉

**Event:** {{ $event->name }}

@if($event->sessions->min('session_date'))
**Date:** {{ \Carbon\Carbon::parse($event->sessions->min('session_date'))->format('M j, Y') }}
@endif

@component('mail::button', ['url' => route('events.show', $event->public_id)])
View Event
@endcomponent

Thanks for following {{ $organizer->name }}!  
{{ config('app.name') }}
@endcomponent
<p>This is an automated message. Please do not reply.</p>
