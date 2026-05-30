<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Console\Commands\DemoCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpireSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVariantsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\RecountCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ReindexCommand;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkExpired;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;

beforeEach(function (): void {
    Artisan::registerCommand(new DemoCommand);
    Artisan::registerCommand(new ExpireSharesCommand);
    Artisan::registerCommand(new PruneSharesCommand);
    Artisan::registerCommand(new PruneVariantsCommand);
    Artisan::registerCommand(new RecountCommand);
    Artisan::registerCommand(new ReindexCommand);
});

it('runs media-library:demo without errors', function (): void {
    expect(Artisan::call('media-library:demo'))->toBe(0);
});

it('runs media-library:expire-shares without errors and dispatches events', function (): void {
    Event::fake([ShareLinkExpired::class]);
    ShareLink::factory()->create(['expires_at' => now()->subHour()]);
    ShareLink::factory()->create(['expires_at' => now()->addHour()]);

    expect(Artisan::call('media-library:expire-shares'))->toBe(0);
    Event::assertDispatchedTimes(ShareLinkExpired::class, 1);
});

it('runs media-library:prune-shares hard-deleting old expired links', function (): void {
    config()->set('media-library.share_links.prune_after_days', 30);

    $stale = ShareLink::factory()->create(['expires_at' => now()->subDays(60)]);
    $fresh = ShareLink::factory()->create(['expires_at' => now()->subDays(5)]);

    expect(Artisan::call('media-library:prune-shares'))->toBe(0);
    expect(ShareLink::query()->withTrashed()->find($stale->id))->toBeNull();
    expect(ShareLink::query()->find($fresh->id))->not->toBeNull();
});

it('runs media-library:prune-variants deleting unused entries', function (): void {
    config()->set('media-library.variants.unused_days', 7);
    $item = MediaLibraryItem::factory()->create();

    $old = MediaLibraryVariant::factory()->create([
        'item_id' => $item->id,
        'last_used_at' => now()->subDays(30),
        'generated_at' => now()->subDays(40),
    ]);
    $recent = MediaLibraryVariant::factory()->create([
        'item_id' => $item->id,
        'last_used_at' => now(),
        'generated_at' => now(),
    ]);

    expect(Artisan::call('media-library:prune-variants'))->toBe(0);
    expect(MediaLibraryVariant::query()->find($old->id))->toBeNull();
    expect(MediaLibraryVariant::query()->find($recent->id))->not->toBeNull();
});

it('runs media-library:recount updating item_count for folders', function (): void {
    $folder = MediaLibraryFolder::factory()->create([
        'item_count' => 999,
    ]);

    MediaLibraryItem::factory()->count(3)->create(['folder_id' => $folder->id]);

    expect(Artisan::call('media-library:recount'))->toBe(0);
    expect($folder->fresh()?->item_count)->toBe(3);
});

it('runs media-library:reindex as a no-op when no ScoutAdapter is configured', function (): void {
    config()->set('media-library.contracts.scout', null);
    expect(Artisan::call('media-library:reindex'))->toBe(0);
});
