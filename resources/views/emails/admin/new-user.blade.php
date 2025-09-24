@component('mail::message')
# New user signed up

**Name:** {{ $user->name }}  
**Email:** {{ $user->email }}

@component('mail::button', ['url' => config('app.url').'/admin/users'])
Open Admin
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
