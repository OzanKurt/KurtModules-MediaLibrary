<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates every media_library_* table', function () {
    foreach ([
        'media_library_folders',
        'media_library_storage',
        'media_library_items',
        'media_library_tags',
        'media_library_item_tag',
        'media_library_attachments',
        'media_library_saved_searches',
        'media_library_versions',
        'media_library_variants',
        'media_library_pending_uploads',
        'media_library_share_links',
        'media_library_access_log',
        'media_library_folder_permissions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing {$table}");
    }
});
