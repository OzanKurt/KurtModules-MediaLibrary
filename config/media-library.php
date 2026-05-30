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
        'exif' => DefaultExifExtractor::class,
        'blurhash' => InterventionBlurhashGenerator::class,
        'palette' => InterventionPaletteExtractor::class,
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

    'share_links' => [
        'prune_after_days' => 30,
    ],

    'notifications' => [
        'enabled' => false,
        'channels' => ['mail', 'database'],
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
        'item' => MediaLibraryItem::class,
        'folder' => MediaLibraryFolder::class,
        'tag' => MediaLibraryTag::class,
        'attachment' => MediaLibraryAttachment::class,
        'share_link' => ShareLink::class,
    ],
];
