@component('mail::message')
# A media library item you follow was replaced

Item `{{ $item->filename }}` (#{{ $item->id }}) has just been replaced with a new file.

@if ($changelog)
**Changelog:** {{ $changelog }}
@endif

The item's id is stable, so all existing attachments continue to resolve to the new file.

Thanks,
{{ config('app.name') }}
@endcomponent
