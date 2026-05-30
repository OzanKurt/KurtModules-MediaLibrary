<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Notifications\ItemReplaced;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app['view']->addNamespace('media-library', __DIR__.'/../../../resources/views');
});

it('respects configured notification channels', function (): void {
    config()->set('media-library.notifications.channels', ['mail', 'database']);

    $item = MediaLibraryItem::factory()->create();
    $notification = new ItemReplaced($item, previousSpatieMediaId: 12, changelog: 'rev');

    expect($notification->via(new StubUser))->toBe(['mail', 'database']);
});

it('persists item id, previous spatie media id, and changelog in the database payload', function (): void {
    $item = MediaLibraryItem::factory()->create();
    $notification = new ItemReplaced($item, previousSpatieMediaId: 99, changelog: 'fixed');

    $payload = $notification->toDatabase(new StubUser);

    expect($payload)->toHaveKey('item_id', $item->id);
    expect($payload)->toHaveKey('previous_spatie_media_id', 99);
    expect($payload)->toHaveKey('changelog', 'fixed');
});

it('points the mail view at the item-replaced template', function (): void {
    $item = MediaLibraryItem::factory()->create();
    $notification = new ItemReplaced($item, previousSpatieMediaId: 1);

    $mail = $notification->toMail(new StubUser);

    expect($mail->view)->toBe('media-library::notifications.item-replaced');
});
