<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Notifications\LargeUploadCompleted;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app['view']->addNamespace('media-library', __DIR__.'/../../../resources/views');
});

it('respects configured notification channels', function (): void {
    config()->set('media-library.notifications.channels', ['mail']);

    $item = MediaLibraryItem::factory()->create();
    $notification = new LargeUploadCompleted($item);

    expect($notification->via(new StubUser))->toBe(['mail']);
});

it('includes filename and byte size in the database payload', function (): void {
    $item = MediaLibraryItem::factory()->create([
        'filename' => 'huge.zip',
        'byte_size' => 12345,
    ]);

    $notification = new LargeUploadCompleted($item);
    $payload = $notification->toDatabase(new StubUser);

    expect($payload)->toHaveKey('item_id', $item->id);
    expect($payload)->toHaveKey('filename', 'huge.zip');
    expect($payload)->toHaveKey('byte_size', 12345);
});

it('points the mail view at the large-upload-completed template', function (): void {
    $item = MediaLibraryItem::factory()->create();
    $notification = new LargeUploadCompleted($item);

    $mail = $notification->toMail(new StubUser);

    expect($mail->view)->toBe('media-library::notifications.large-upload-completed');
});
