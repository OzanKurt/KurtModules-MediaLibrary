<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;

it('generateToken returns a 32-char url-safe string', function (): void {
    $signer = new ShareLinkSigner;
    $token = $signer->generateToken();

    expect($token)->toBeString();
    // 24 random bytes → base64 = 32 chars; rtrim '=' leaves 32 url-safe chars.
    expect(strlen($token))->toBe(32);
    expect($token)->toMatch('/^[A-Za-z0-9_\-]+$/');
});

it('generateToken returns unique tokens across many calls', function (): void {
    $signer = new ShareLinkSigner;
    $tokens = [];
    for ($i = 0; $i < 500; $i++) {
        $tokens[$signer->generateToken()] = true;
    }

    expect(count($tokens))->toBe(500);
});

it('url() builds a full URL with the configured prefix', function (): void {
    config()->set('media-library.routes.share_prefix', 'media-library/share');
    $signer = new ShareLinkSigner;

    $url = $signer->url('abc123');

    expect($url)->toEndWith('/media-library/share/abc123');
});

it('url() trims slashes from the configured prefix', function (): void {
    config()->set('media-library.routes.share_prefix', '/foo/bar/');
    $signer = new ShareLinkSigner;

    $url = $signer->url('tok');

    expect($url)->toEndWith('/foo/bar/tok');
    expect($url)->not->toContain('//foo');
});

it('url() uses the configured prefix verbatim', function (): void {
    config()->set('media-library.routes.share_prefix', 'shared');
    $signer = new ShareLinkSigner;

    $url = $signer->url('xyz');

    expect($url)->toEndWith('/shared/xyz');
});
