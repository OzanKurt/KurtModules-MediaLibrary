<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;

beforeEach(function (): void {
    // Stub a request with a known IP + user agent so the logger has something to capture.
    $request = Request::create('/share/abc', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.5',
        'HTTP_USER_AGENT' => 'Test/UA',
    ]);
    app()->instance('request', $request);
});

it('writes a row with action + ip + user_agent', function (): void {
    config()->set('media-library.access_log.enabled', true);
    config()->set('media-library.access_log.on_view', true);

    $item = MediaLibraryItem::factory()->create();
    $link = ShareLink::factory()->create(['item_id' => $item->id]);

    $logger = new AccessLogger;
    $logger->log($item, $link, null, AccessAction::View);

    $row = AccessLogEntry::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->action)->toBe(AccessAction::View);
    expect($row->item_id)->toBe($item->id);
    expect($row->share_link_id)->toBe($link->id);
    expect($row->ip)->toBe('203.0.113.5');
    expect($row->user_agent)->toBe('Test/UA');
});

it('skips writing when access_log.enabled is false', function (): void {
    config()->set('media-library.access_log.enabled', false);
    $item = MediaLibraryItem::factory()->create();

    $before = AccessLogEntry::query()->count();

    $logger = new AccessLogger;
    $logger->log($item, null, null, AccessAction::View);

    expect(AccessLogEntry::query()->count())->toBe($before);
});

it('skips View when access_log.on_view is false', function (): void {
    config()->set('media-library.access_log.enabled', true);
    config()->set('media-library.access_log.on_view', false);
    config()->set('media-library.access_log.on_download', true);

    $item = MediaLibraryItem::factory()->create();

    $before = AccessLogEntry::query()->count();

    $logger = new AccessLogger;
    $logger->log($item, null, null, AccessAction::View);

    expect(AccessLogEntry::query()->count())->toBe($before);
});

it('writes Download when on_view is off but on_download is on', function (): void {
    config()->set('media-library.access_log.enabled', true);
    config()->set('media-library.access_log.on_view', false);
    config()->set('media-library.access_log.on_download', true);

    $item = MediaLibraryItem::factory()->create();

    $logger = new AccessLogger;
    $logger->log($item, null, null, AccessAction::Download);

    $row = AccessLogEntry::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->action)->toBe(AccessAction::Download);
});

it('skips Download when access_log.on_download is false', function (): void {
    config()->set('media-library.access_log.enabled', true);
    config()->set('media-library.access_log.on_view', true);
    config()->set('media-library.access_log.on_download', false);

    $item = MediaLibraryItem::factory()->create();

    $before = AccessLogEntry::query()->count();

    $logger = new AccessLogger;
    $logger->log($item, null, null, AccessAction::Download);

    expect(AccessLogEntry::query()->count())->toBe($before);
});

it('writes a row with null item + null link for orphan log entries', function (): void {
    config()->set('media-library.access_log.enabled', true);

    $logger = new AccessLogger;
    $logger->log(null, null, null, AccessAction::Delete);

    $row = AccessLogEntry::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->item_id)->toBeNull();
    expect($row->share_link_id)->toBeNull();
    expect($row->action)->toBe(AccessAction::Delete);
});
