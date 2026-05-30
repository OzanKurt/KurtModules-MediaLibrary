<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Notifications\ShareLinkAccessed;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app['view']->addNamespace('media-library', __DIR__.'/../../../resources/views');
});

it('respects channel configuration', function (): void {
    config()->set('media-library.notifications.channels', ['database']);

    $link = ShareLink::factory()->create();
    $notification = new ShareLinkAccessed($link, '127.0.0.1', 'curl/8');

    expect($notification->via(new StubUser))->toBe(['database']);
});

it('captures ip + user agent + accessed_at in the database payload', function (): void {
    $link = ShareLink::factory()->create(['token' => 'tok42']);
    $notification = new ShareLinkAccessed($link, '10.0.0.5', 'mozilla');

    $payload = $notification->toDatabase(new StubUser);

    expect($payload)->toHaveKey('share_link_id', $link->id);
    expect($payload)->toHaveKey('token', 'tok42');
    expect($payload)->toHaveKey('ip', '10.0.0.5');
    expect($payload)->toHaveKey('user_agent', 'mozilla');
    expect($payload)->toHaveKey('accessed_at');
});

it('points the mail view at the share-link-accessed template', function (): void {
    $link = ShareLink::factory()->create();
    $notification = new ShareLinkAccessed($link);

    $mail = $notification->toMail(new StubUser);

    expect($mail->view)->toBe('media-library::notifications.share-link-accessed');
});
