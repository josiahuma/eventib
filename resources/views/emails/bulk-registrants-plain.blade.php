Message from {{ $organizerName }} regarding {{ $event->name }}:

{{ strip_tags($messageHtml) }}

---

You received this because you registered for {{ $event->name }}.
@isset($organizerEmail)
To unsubscribe, please contact the event organizer at {{ $organizerEmail }}.
@else
To unsubscribe, please contact the event organizer.
@endisset
