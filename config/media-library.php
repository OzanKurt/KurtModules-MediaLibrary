<?php

declare(strict_types=1);
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Storage\Extractors\DefaultExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;

return [
    'subject_resolver' => DefaultSubjectResolver::class,

    'contracts' => [
        // Extractor contract bindings. Each is resolved from the container when
        // set to a class-string and left unbound (skipped gracefully) when null.
        //
        // - blurhash + palette run SYNCHRONOUSLY on every image upload (they feed
        //   MetadataExtractor for immediate placeholder + colour data).
        // - exif runs ASYNCHRONOUSLY in the ExtractMediaMetadata job and pulls
        //   dimensions + full EXIF (incl. GPS) via PHP's exif_read_data().
        // - ocr / ai_tagger / scout are pluggable extension points. The package
        //   ships no engine for them: point these at your own implementation (the
        //   Null* stubs below are safe no-op references) and the async job will
        //   run them. Left null, each step is skipped.
        'blurhash' => InterventionBlurhashGenerator::class,
        'palette' => InterventionPaletteExtractor::class,
        'exif' => DefaultExifExtractor::class,
        'ocr' => null,        // \Kurt\Modules\MediaLibrary\Storage\Extractors\NullOcrExtractor::class
        'ai_tagger' => null,  // \Kurt\Modules\MediaLibrary\Storage\Extractors\NullAiTagger::class
        'scout' => null,      // \Kurt\Modules\MediaLibrary\Search\Support\NullScoutAdapter::class
    ],

    'extractors' => [
        // Re-introduced in 2.2.0 (round-2 removed the earlier, dead sync/async
        // lists that nothing dispatched). This block now drives a REAL queued
        // job: after a successful upload / replace the coordinators dispatch
        // ExtractMediaMetadata, which runs the `pipeline` steps below.
        //
        // Dispatch mode:
        //   'queued' — push ExtractMediaMetadata onto the queue (ShouldQueue).
        //   'sync'   — run it inline in the request (dispatchSync).
        'dispatch' => env('MEDIA_LIBRARY_EXTRACTORS_DISPATCH', 'queued'),

        // Optional queue connection + name for the queued dispatch mode.
        'connection' => env('MEDIA_LIBRARY_EXTRACTORS_CONNECTION'),
        'queue' => env('MEDIA_LIBRARY_EXTRACTORS_QUEUE'),

        // Ordered steps the job runs over the stored media. Each name maps to a
        // `contracts` binding above; unbound steps are skipped gracefully.
        'pipeline' => ['exif', 'ocr', 'ai_tagger', 'scout'],
    ],

    'uploads' => [
        // Media is served through the share-link controller, which enforces
        // share abilities, folder ACL, policies, and access logging. Keep this
        // on a PRIVATE disk (e.g. 'local' or a private S3 bucket). A 'public'
        // disk lets spatie serve raw bytes at a guessable /storage/{id}/{file}
        // path, bypassing every access-control check above. Only use 'public'
        // if you explicitly do not want access control on media.
        'disk' => env('MEDIA_LIBRARY_DISK', 'local'),
        'max_size_kb' => 100_000,
        'allowed_mimes' => ['image/*', 'video/*', 'audio/*', 'application/pdf', 'application/zip'],
        'presigned_ttl_seconds' => 900,
        'expire_pending_after_seconds' => 3600,
    ],

    'conversions' => [
        'thumb' => ['width' => 320, 'height' => 320, 'fit' => 'crop'],
        'cover' => ['width' => 1200, 'height' => 630, 'fit' => 'crop'],
        'social' => ['width' => 1600, 'height' => 900, 'fit' => 'crop'],
    ],

    'variants' => [
        'unused_days' => 30,
        'max_per_item' => 100,
    ],

    'versions' => [
        'keep_old' => 10,
        'hard_delete_old_files' => false,
    ],

    'http' => [
        // Out-of-the-box REST API, built on the Core API kit. Safe-by-default:
        // in `headless` mode NOTHING is registered (no routes, no rate limiter).
        // Flip to `api` (MEDIA_LIBRARY_HTTP_MODE=api) to expose the JSON API.
        //
        //   headless — no API surface (default).
        //   api      — register the read + write REST routes.
        //   ui       — same as api (reserved for a future first-party UI).
        //
        // Reads are ACL-scoped (folder/item Policies); writes require the auth
        // middleware AND pass the same folder-ACL Policies per method. Uploads
        // go through the UploadCoordinator so the hashing / extractor pipeline /
        // GDPR bookkeeping all run — the API is a thin adapter, never a bypass.
        'mode' => env('MEDIA_LIBRARY_HTTP_MODE', 'headless'),
        'prefix' => 'api/media',
        'middleware' => ['api'],
        'auth_middleware' => ['auth'],
        'rate_limit' => '60,1',
    ],

    'routes' => [
        'share_enabled' => true,
        'share_prefix' => 'media-library/share',

        // Rate limit applied to the public share endpoint, in Laravel's
        // `throttle` middleware syntax ("max,minutes"). Share tokens are bearer
        // credentials, so throttling blunts token-guessing / enumeration.
        'share_throttle' => env('MEDIA_LIBRARY_SHARE_THROTTLE', '60,1'),
    ],

    'share_links' => [
        'prune_after_days' => 30,
    ],

    'shares' => [
        // Share links are bearer credentials: by default anyone holding a valid,
        // unexpired, un-revoked token may access the target, and `invitee_email`
        // is purely decorative (it records who the link was sent to). Flip this
        // to `true` to require the requester to be authenticated as the named
        // invitee before a link that sets `invitee_email` will resolve. Links
        // with a null `invitee_email` stay bearer regardless of this flag.
        'enforce_invitee' => env('MEDIA_LIBRARY_ENFORCE_INVITEE', false),

        // Maximum number of items returned by a folder-share JSON listing. Keeps
        // a share on a large folder from producing an unbounded response.
        'folder_listing_limit' => 500,
    ],

    'notifications' => [
        'enabled' => false,
        'channels' => ['mail', 'database'],
    ],

    'access_log' => [
        'enabled' => true,
        'on_view' => true,
        'on_download' => true,

        // Access-log retention. `media-library:prune-access-log` hard-deletes
        // entries older than this many days (GDPR storage-limitation). Set to 0
        // to disable pruning and keep the log indefinitely.
        'prune_after_days' => 365,
    ],

    'audit' => [
        'enabled' => true,
    ],

    'models' => [
        'item' => MediaLibraryItem::class,
        'folder' => MediaLibraryFolder::class,
        'tag' => MediaLibraryTag::class,
        'attachment' => MediaLibraryAttachment::class,
        'share_link' => ShareLink::class,
    ],
];
