@component('mail::message')
# A media library item has been shared with you

You can access the shared item using the link below.

@component('mail::button', ['url' => ''])
View Item
@endcomponent

This link expires {{ $link->expires_at?->diffForHumans() ?? 'never' }}.

Thanks,
{{ config('app.name') }}
@endcomponent
