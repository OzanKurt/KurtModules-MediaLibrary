<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

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

function shareRequestAs(string $token, ?StubUser $user): Request
{
    $request = Request::create('/share/'.$token, 'GET');

    if ($user !== null) {
        $request->setUserResolver(fn () => $user);
    }

    return $request;
}

it('ignores invitee_email when enforcement is off (bearer semantics)', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.shares.enforce_invitee', false);

    $item = uploadItemOnDisk('public');
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'abilities' => ['view', 'download'],
        'invitee_email' => 'guest@test.dev',
    ]);

    // No authenticated user, yet the token alone grants access.
    $response = buildShareController()->show($link->token, shareRequestAs($link->token, null));

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
    expect($response->getStatusCode())->toBe(200);
});

it('allows a matching invitee when enforcement is on (case-insensitive)', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.shares.enforce_invitee', true);

    $item = uploadItemOnDisk('public');
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'abilities' => ['view', 'download'],
        'invitee_email' => 'Guest@Test.dev',
    ]);

    $invitee = StubUser::create(['id' => 91, 'email' => 'guest@test.dev']);

    $response = buildShareController()->show($link->token, shareRequestAs($link->token, $invitee));

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
    expect($response->getStatusCode())->toBe(200);
});

it('denies a mismatched invitee when enforcement is on', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.shares.enforce_invitee', true);

    $item = uploadItemOnDisk('public');
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'abilities' => ['view', 'download'],
        'invitee_email' => 'guest@test.dev',
    ]);

    $stranger = StubUser::create(['id' => 92, 'email' => 'stranger@test.dev']);

    buildShareController()->show($link->token, shareRequestAs($link->token, $stranger));
})->throws(HttpException::class, 'invitee_mismatch');

it('denies an unauthenticated requester when enforcement is on', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.shares.enforce_invitee', true);

    $item = uploadItemOnDisk('public');
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'abilities' => ['view', 'download'],
        'invitee_email' => 'guest@test.dev',
    ]);

    buildShareController()->show($link->token, shareRequestAs($link->token, null));
})->throws(HttpException::class, 'invitee_mismatch');

it('returns a bounded JSON listing for a folder share (was always 410)', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');

    $folder = MediaLibraryFolder::factory()->create();
    MediaLibraryItem::factory()->count(3)->create(['folder_id' => $folder->id]);
    // An item in a different folder must not leak into the listing.
    MediaLibraryItem::factory()->create(['folder_id' => null]);

    $link = ShareLink::factory()->forFolder($folder->id)->create(['abilities' => ['view']]);

    $response = buildShareController()->show($link->token, Request::create('/share/'.$link->token, 'GET'));

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    /** @var array<string, mixed> $payload */
    $payload = $response->getData(true);
    expect($payload['count'])->toBe(3);
    expect($payload['items'])->toHaveCount(3);
    expect($payload['folder']['id'])->toBe($folder->id);
    expect($payload['abilities'])->toBe(['view']);
});

it('honours the folder_listing_limit for a folder share', function (): void {
    Event::fake();
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
    config()->set('media-library.shares.folder_listing_limit', 2);

    $folder = MediaLibraryFolder::factory()->create();
    MediaLibraryItem::factory()->count(5)->create(['folder_id' => $folder->id]);

    $link = ShareLink::factory()->forFolder($folder->id)->create(['abilities' => ['view']]);

    $response = buildShareController()->show($link->token, Request::create('/share/'.$link->token, 'GET'));

    /** @var array<string, mixed> $payload */
    $payload = $response->getData(true);
    expect($payload['count'])->toBe(2);
});

it('denies a folder share when the requested ability is not granted', function (): void {
    Event::fake();
    $folder = MediaLibraryFolder::factory()->create();
    $link = ShareLink::factory()->forFolder($folder->id)->create(['abilities' => ['view']]);

    buildShareController()->show(
        $link->token,
        Request::create('/share/'.$link->token, 'GET', ['download' => '1']),
    );
})->throws(HttpException::class, 'ability_not_granted');
