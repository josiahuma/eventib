@component('mail::message')
# You have a new follower

**{{ $follower->name }}** is now following you 

@component('mail::button', ['url' => route('organizers.show', $organizer->slug)])
View Organizer Page
@endcomponent

@endcomponent
