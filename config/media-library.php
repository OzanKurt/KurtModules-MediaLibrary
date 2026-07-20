<?php

declare(strict_types=1);
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;

return [
    'subject_resolver' => DefaultSubjectResolver::class,

    'contracts' => [
        // Runtime extraction the package actually wires: blurhash + palette run
        // synchronously on every image upload. ocr / ai_tagger / scout are
        // pluggable extension points that stay unbound (null) until you supply an
        // implementation and a job/command to invoke it - nothing runs them for you.
        'blurhash' => InterventionBlurhashGenerator::class,
        'palette' => InterventionPaletteExtractor::class,
        'ocr' => null,
        'ai_tagger' => null,
        'scout' => null,
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
