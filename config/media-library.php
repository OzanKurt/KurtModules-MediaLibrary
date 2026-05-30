<?php

declare(strict_types=1);

return [
    'subject_resolver' => Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver::class,

    'contracts' => [
        'exif' => Kurt\Modules\MediaLibrary\Storage\Extractors\DefaultExifExtractor::class,
        'blurhash' => Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator::class,
        'palette' => Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor::class,
        'ocr' => null,
        'ai_tagger' => null,
        'scout' => null,
    ],

    'uploads' => [
        'disk' => env('MEDIA_LIBRARY_DISK', 'public'),
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
    ],

    'extractors' => [
        'sync' => ['dimensions', 'palette', 'blurhash'],   // run in request
        'async' => ['exif', 'ai_tagger', 'ocr'],            // dispatched as jobs
    ],

    'access_log' => [
        'enabled' => true,
        'on_view' => true,
        'on_download' => true,
    ],

    'audit' => [
        'enabled' => true,
    ],

    'models' => [
        'item' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem::class,
        'folder' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder::class,
        'tag' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag::class,
        'attachment' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment::class,
        'share_link' => Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink::class,
    ],
];
