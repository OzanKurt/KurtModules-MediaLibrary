<?php

declare(strict_types=1);

use kornrunner\Blurhash\Blurhash;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;

beforeAll(function (): void {
    $dir = __DIR__.'/../../../fixtures';
    if (! is_dir($dir)) {
        @mkdir($dir, recursive: true);
    }

    $png = $dir.'/test.png';
    if (! file_exists($png) && function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(64, 64);
        if ($im !== false) {
            $bg = imagecolorallocate($im, 200, 100, 50);
            if ($bg !== false) {
                imagefilledrectangle($im, 0, 0, 64, 64, $bg);
            }
            imagepng($im, $png);
            imagedestroy($im);
        }
    }
});

it('returns a non-empty blurhash for a valid image', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    if (! class_exists(Blurhash::class)) {
        $this->markTestSkipped('kornrunner/blurhash not installed.');
    }

    $path = __DIR__.'/../../../fixtures/test.png';
    if (! file_exists($path)) {
        $this->markTestSkipped('Test fixture not available.');
    }

    $generator = new InterventionBlurhashGenerator;
    $hash = $generator->generate($path);

    expect($hash)->toBeString();
    expect($hash)->not->toBe('');
    // Blurhash strings encode size in first chars; with 4x4 components, length is well-defined.
    expect(strlen($hash))->toBeGreaterThan(6);
});

it('returns an empty string for a missing file', function (): void {
    $generator = new InterventionBlurhashGenerator;

    expect($generator->generate(__DIR__.'/missing-image.png'))->toBe('');
});

it('returns an empty string for an unreadable path', function (): void {
    $generator = new InterventionBlurhashGenerator;

    // Directory path is not a readable image.
    expect($generator->generate(__DIR__))->toBe('');
});
