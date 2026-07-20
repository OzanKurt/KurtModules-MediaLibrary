<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Http\Controllers\ShareLinkController;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

function buildShareController(): ShareLinkController
{
    return new ShareLinkController(new ShareLinkResolver, new AccessLogger);
}

function uploadItemOnDisk(string $disk): MediaLibraryItem
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);

    $coordinator = new UploadCoordinator(new DefaultSubjectResolver, new MetadataExtractor($blurhash, $palette));

    $owner = StubUser::create(['id' => 71, 'email' => 'sharer@test.dev']);

    return $coordinator->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'shared.png', 'image/png', null, true),
        $owner,
    );
}

it('serves a local-disk item inline through the controller (view ability)', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');

    $item = uploadItemOnDisk('public');
    $link = ShareLink::factory()->create(['item_id' => $item->id, 'abilities' => ['view', 'download']]);

    $response = buildShareController()->show($link->token, Request::create('/share/'.$link->token, 'GET'));

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
    expect($response->getStatusCode())->toBe(200);
});

it('streams a remote-disk item through the disk instead of getPath() (view ability)', function (): void {
    Event::fake();
    Storage::fake('s3');
    config()->set('media-library.uploads.disk', 's3');

    $item = uploadItemOnDisk('s3');

    // Force the controller down the remote branch: the fake is local-backed, but
    // a real s3 disk has no filesystem path, so the controller must stream via
    // the disk's own response()/download() helpers rather than $media->getPath().
    config()->set('filesystems.disks.s3.driver', 's3');

    $link = ShareLink::factory()->create(['item_id' => $item->id, 'abilities' => ['view', 'download']]);

    $response = buildShareController()->show($link->token, Request::create('/share/'.$link->token, 'GET'));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->getStatusCode())->toBe(200);
});

it('streams a remote-disk download with an attachment disposition', function (): void {
    Event::fake();
    Storage::fake('s3');
    config()->set('media-library.uploads.disk', 's3');

    $item = uploadItemOnDisk('s3');
    config()->set('filesystems.disks.s3.driver', 's3');

    $link = ShareLink::factory()->create(['item_id' => $item->id, 'abilities' => ['view', 'download']]);

    $response = buildShareController()->show(
        $link->token,
        Request::create('/share/'.$link->token, 'GET', ['download' => '1']),
    );

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});
