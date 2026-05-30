<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

it('persists an access log row with action enum cast', function () {
    $item = MediaLibraryItem::factory()->create();
    $link = ShareLink::factory()->create(['item_id' => $item->id]);

    $row = AccessLogEntry::factory()->create([
        'item_id' => $item->id,
        'share_link_id' => $link->id,
        'action' => AccessAction::Download,
        'ip' => '203.0.113.7',
    ]);

    expect($row->action)->toBe(AccessAction::Download);
    expect($row->item?->id)->toBe($item->id);
    expect($row->shareLink?->id)->toBe($link->id);
    expect($row->ip)->toBe('203.0.113.7');
});

it('item and share link can be null for orphan log rows', function () {
    $row = AccessLogEntry::factory()->create([
        'item_id' => null,
        'share_link_id' => null,
        'action' => AccessAction::View,
    ]);

    expect($row->item)->toBeNull();
    expect($row->shareLink)->toBeNull();
    expect($row->action)->toBe(AccessAction::View);
});
