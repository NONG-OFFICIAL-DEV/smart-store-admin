@component('mail::message')
# {{ $title }}

{{ $body }}

@component('mail::button', ['url' => rtrim(config('app.frontend_url'), '/').'/notifications'])
Open Notifications
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
