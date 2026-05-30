<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;

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

it('returns dominant + palette for a valid image', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $path = __DIR__.'/../../../fixtures/test.png';
    if (! file_exists($path)) {
        $this->markTestSkipped('Test fixture not available.');
    }

    $extractor = new InterventionPaletteExtractor;
    $result = $extractor->extract($path);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['dominant', 'palette']);
    expect($result['dominant'])->toMatch('/^#[0-9a-f]{6}$/i');
    expect($result['palette'])->toBeArray();
    expect(count($result['palette']))->toBeGreaterThanOrEqual(1);

    foreach ($result['palette'] as $hex) {
        expect($hex)->toMatch('/^#[0-9a-f]{6}$/i');
    }
});

it('returns defaults for a missing file', function (): void {
    $extractor = new InterventionPaletteExtractor;
    $result = $extractor->extract(__DIR__.'/missing.png');

    expect($result)->toBe(['dominant' => '#000000', 'palette' => []]);
});

it('returns defaults for an unreadable path', function (): void {
    $extractor = new InterventionPaletteExtractor;

    expect($extractor->extract(__DIR__))->toBe(['dominant' => '#000000', 'palette' => []]);
});
