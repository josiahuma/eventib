@component('mail::message')
# You have a new follower

**Organizer:** {{ $organizer->name }}  
**Follower:** {{ $follower->name }} ({{ $follower->email }})

@component('mail::button', ['url' => route('organizers.show', $organizer->slug)])
View Organizer
@endcomponent

@endcomponent
