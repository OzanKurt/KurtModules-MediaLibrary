<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Kurt\Modules\MediaLibrary\Notifications\ShareLinkCreated;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app['view']->addNamespace('media-library', __DIR__.'/../../../resources/views');
});

it('respects channel configuration in the via() method', function (): void {
    config()->set('media-library.notifications.channels', ['mail']);

    $link = ShareLink::factory()->create();
    $notification = new ShareLinkCreated($link);

    expect($notification->via(new StubUser))->toBe(['mail']);
});

it('returns the share link payload from toDatabase()', function (): void {
    $link = ShareLink::factory()->create([
        'token' => 'abc123',
        'expires_at' => now()->addDay(),
    ]);

    $notification = new ShareLinkCreated($link);
    $payload = $notification->toDatabase(new StubUser);

    expect($payload)->toHaveKey('share_link_id', $link->id);
    expect($payload)->toHaveKey('token', 'abc123');
    expect($payload)->toHaveKey('item_id', $link->item_id);
    expect($payload)->toHaveKey('expires_at');
});

it('queues a notification when sent through the facade', function (): void {
    Notification::fake();
    config()->set('media-library.notifications.channels', ['mail']);

    $link = ShareLink::factory()->create();
    $user = new StubUser(['id' => 1]);
    $user->forceFill(['id' => 1]);

    Notification::send([$user], new ShareLinkCreated($link));

    Notification::assertSentTo($user, ShareLinkCreated::class, function (ShareLinkCreated $n) use ($link): bool {
        return $n->link->id === $link->id;
    });
});

it('renders the toMail() view without throwing', function (): void {
    $link = ShareLink::factory()->create();
    $notification = new ShareLinkCreated($link);

    $mail = $notification->toMail(new StubUser);

    expect($mail->view)->toBe('media-library::notifications.share-link-created');
});
