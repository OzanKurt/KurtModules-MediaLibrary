<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.routes.share_enabled', true);
    config()->set('media-library.routes.share_prefix', 'media-library/share');
    config()->set('media-library.access_log.enabled', true);
    config()->set('media-library.access_log.on_view', true);
});

it('serves the underlying file on a valid view share token', function (): void {
    $owner = StubUser::create(['email' => 'shareview@test.dev']);
    $library = app(MediaLibrary::class);

    $item = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'shared.png', 'image/png', null, true),
        $owner,
    );

    $url = $library->shareItem($item, expiresInSeconds: 3600, abilities: ['view']);
    expect($url)->toBeString()->toContain('/media-library/share/');

    $token = (string) substr($url, (int) strrpos($url, '/') + 1);
    $path = '/media-library/share/'.$token;

    $response = $this->get($path);
    $response->assertOk();

    $link = ShareLink::query()->where('token', $token)->first();
    expect($link)->not->toBeNull();
    expect($link?->access_count)->toBe(1);
    expect($link?->last_accessed_at)->not->toBeNull();

    $logRow = AccessLogEntry::query()->latest('id')->first();
    expect($logRow)->not->toBeNull();
    expect($logRow?->item_id)->toBe($item->id);
    expect($logRow?->share_link_id)->toBe($link?->id);
    expect($logRow?->action)->toBe(AccessAction::View);
});

it('returns 403 when the requested ability is not granted', function (): void {
    $owner = StubUser::create(['email' => 'denyability@test.dev']);
    $library = app(MediaLibrary::class);

    $item = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'forbidden.png', 'image/png', null, true),
        $owner,
    );

    $url = $library->shareItem($item, expiresInSeconds: 3600, abilities: ['view']);
    $token = (string) substr($url, (int) strrpos($url, '/') + 1);

    $response = $this->get('/media-library/share/'.$token.'?download=1');
    $response->assertForbidden();
});
