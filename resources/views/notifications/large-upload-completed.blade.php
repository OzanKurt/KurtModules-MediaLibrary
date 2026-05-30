@component('mail::message')
# Your upload has finished processing

Your file `{{ $item->filename }}` has finished uploading and is now available in your media library.

- Item id: #{{ $item->id }}
- Size: {{ number_format((int) $item->byte_size / 1024, 1) }} KB
- Type: {{ $item->mime_type }}

Thanks,
{{ config('app.name') }}
@endcomponent
