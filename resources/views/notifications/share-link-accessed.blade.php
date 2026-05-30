@component('mail::message')
# Your shared link was accessed

A media library link you created has just been accessed.

- Token: `{{ $link->token }}`
- IP: {{ $ip ?? 'unknown' }}
- User agent: {{ $userAgent ?? 'unknown' }}

If this access was unexpected you can revoke the link at any time.

Thanks,
{{ config('app.name') }}
@endcomponent
