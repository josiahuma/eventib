<x-mail::message>
# A new plan from {{ $event->organizer?->name }}

{{ $event->name }}

@if($session = $event->sessions->first())
{{ \Illuminate\Support\Carbon::parse($session->session_date)->format('D, d M Y · g:ia') }}
@endif

{{ $event->location }}

<x-mail::button :url="route('events.show', $event)">See the event</x-mail::button>

You opted in to new-event emails from this organiser on Eventib.

[Turn off these alerts]({{ $unsubscribeUrl }}) · [Manage your alerts]({{ route('organizer-alerts.index') }})
</x-mail::message>
