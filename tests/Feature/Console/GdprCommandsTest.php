<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneAccessLogCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PurgeSubjectCommand;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

beforeEach(function (): void {
    Artisan::registerCommand(new PurgeSubjectCommand);
    Artisan::registerCommand(new PruneAccessLogCommand);
});

it('purges all data for a subject while leaving other subjects untouched', function (): void {
    $itemA = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 301]);
    $itemB = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 302]);

    $folderA = MediaLibraryFolder::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 301]);

    $shareA = ShareLink::factory()->create(['item_id' => $itemA->id]);
    $logA = AccessLogEntry::create([
        'item_id' => $itemA->id,
        'action' => AccessAction::View,
        'occurred_at' => now(),
    ]);

    $storageAId = $itemA->storage_id;

    expect(Artisan::call('media-library:purge-subject', ['type' => 'stub_owner', 'id' => 301]))->toBe(0);

    // Subject 301 is fully gone.
    expect(MediaLibraryItem::withTrashed()->find($itemA->id))->toBeNull();
    expect(MediaLibraryStorage::query()->find($storageAId))->toBeNull();
    expect(MediaLibraryFolder::withTrashed()->find($folderA->id))->toBeNull();
    expect(ShareLink::withTrashed()->find($shareA->id))->toBeNull();
    expect(AccessLogEntry::query()->find($logA->id))->toBeNull();

    // Subject 302 is untouched.
    expect(MediaLibraryItem::query()->find($itemB->id))->not->toBeNull();
});

it('purges extracted EXIF/GPS metadata along with the item row', function (): void {
    // GPS coordinates extracted by the pipeline live in the item's `exif` json
    // column, so the subject purge (which force-deletes the item) must remove
    // them — no orphaned location PII may survive.
    $item = MediaLibraryItem::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 555,
        'exif' => [
            'GPSLatitude' => ['51/1', '30/1', '0/1'],
            'GPSLatitudeRef' => 'N',
            'GPSLongitude' => ['0/1', '7/1', '0/1'],
            'GPSLongitudeRef' => 'E',
        ],
    ]);

    expect($item->exif)->toHaveKey('GPSLatitude');

    expect(Artisan::call('media-library:purge-subject', ['type' => 'stub_owner', 'id' => 555]))->toBe(0);

    expect(MediaLibraryItem::withTrashed()->find($item->id))->toBeNull();
});

it('anonymizes access-log viewer identity with --anonymize-log', function (): void {
    $item = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 999]);

    $log = AccessLogEntry::create([
        'item_id' => $item->id,
        'user_id' => 401,
        'action' => AccessAction::View,
        'occurred_at' => now(),
    ]);

    Artisan::call('media-library:purge-subject', ['type' => 'stub_owner', 'id' => 401, '--anonymize-log' => true]);

    expect($log->fresh()?->user_id)->toBeNull();
});

it('prunes access-log entries older than the configured retention', function (): void {
    config()->set('media-library.access_log.prune_after_days', 365);

    $item = MediaLibraryItem::factory()->create();

    $old = AccessLogEntry::create([
        'item_id' => $item->id,
        'action' => AccessAction::View,
        'occurred_at' => now()->subDays(400),
    ]);
    $recent = AccessLogEntry::create([
        'item_id' => $item->id,
        'action' => AccessAction::View,
        'occurred_at' => now()->subDays(10),
    ]);

    expect(Artisan::call('media-library:prune-access-log'))->toBe(0);
    expect(AccessLogEntry::query()->find($old->id))->toBeNull();
    expect(AccessLogEntry::query()->find($recent->id))->not->toBeNull();
});

it('does not prune the access log when retention is disabled (0)', function (): void {
    config()->set('media-library.access_log.prune_after_days', 0);

    $item = MediaLibraryItem::factory()->create();
    $old = AccessLogEntry::create([
        'item_id' => $item->id,
        'action' => AccessAction::View,
        'occurred_at' => now()->subDays(1000),
    ]);

    expect(Artisan::call('media-library:prune-access-log'))->toBe(0);
    expect(AccessLogEntry::query()->find($old->id))->not->toBeNull();
});
