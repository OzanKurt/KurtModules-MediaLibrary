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

it('resolves by the hash of the token, not the plaintext value', function (): void {
    // Store only the hash (plaintext token blanked) so a match can only come
    // from the hashed lookup, proving tokens are matched by hash.
    $link = ShareLink::factory()->create([
        'token' => '',
        'token_hash' => hash('sha256', 'the-real-token'),
    ]);
    $resolver = new ShareLinkResolver;

    expect($resolver->resolve('the-real-token')->id)->toBe($link->id);
});

it('still resolves a legacy link that has no token_hash (backward compat)', function (): void {
    $link = ShareLink::factory()->create([
        'token' => 'legacy-plain-token',
        'token_hash' => null,
    ]);
    $resolver = new ShareLinkResolver;

    expect($resolver->resolve('legacy-plain-token')->id)->toBe($link->id);
});
