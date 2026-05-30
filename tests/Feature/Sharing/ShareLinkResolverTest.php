<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Exceptions\ShareLinkInvalid;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;

it('resolves an active link by token', function (): void {
    $link = ShareLink::factory()->create(['token' => 'active-token-1']);
    $resolver = new ShareLinkResolver;

    $resolved = $resolver->resolve('active-token-1');

    expect($resolved->id)->toBe($link->id);
});

it('throws ShareLinkInvalid for a missing token', function (): void {
    $resolver = new ShareLinkResolver;

    expect(fn () => $resolver->resolve('does-not-exist'))
        ->toThrow(ShareLinkInvalid::class, 'not_found');
});

it('throws ShareLinkInvalid for a revoked link', function (): void {
    ShareLink::factory()->revoked()->create(['token' => 'revoked-token']);
    $resolver = new ShareLinkResolver;

    expect(fn () => $resolver->resolve('revoked-token'))
        ->toThrow(ShareLinkInvalid::class, 'inactive');
});

it('throws ShareLinkInvalid for an expired link', function (): void {
    ShareLink::factory()->expired()->create(['token' => 'expired-token']);
    $resolver = new ShareLinkResolver;

    expect(fn () => $resolver->resolve('expired-token'))
        ->toThrow(ShareLinkInvalid::class, 'inactive');
});

it('resolves a link with no expiry', function (): void {
    $link = ShareLink::factory()->noExpiry()->create(['token' => 'no-expiry']);
    $resolver = new ShareLinkResolver;

    expect($resolver->resolve('no-expiry')->id)->toBe($link->id);
});
