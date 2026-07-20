<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Storage\Extractors\DefaultExifExtractor;

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

    $jpg = $dir.'/test.jpg';
    if (! file_exists($jpg) && function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(64, 64);
        if ($im !== false) {
            $bg = imagecolorallocate($im, 50, 100, 200);
            if ($bg !== false) {
                imagefilledrectangle($im, 0, 0, 64, 64, $bg);
            }
            imagejpeg($im, $jpg, 90);
            imagedestroy($im);
        }
    }
});

it('returns an empty array for a non-existent file', function (): void {
    $extractor = new DefaultExifExtractor;

    expect($extractor->extract(__DIR__.'/does-not-exist.jpg'))->toBe([]);
});

it('returns an empty array when path is a directory', function (): void {
    $extractor = new DefaultExifExtractor;

    expect($extractor->extract(__DIR__))->toBe([]);
});

it('extracts dimensions from a readable image', function (): void {
    $path = __DIR__.'/../../../fixtures/test.jpg';

    if (! file_exists($path)) {
        $this->markTestSkipped('GD not available to generate fixture.');
    }

    $result = (new DefaultExifExtractor)->extract($path);

    expect($result)->toBeArray()
        ->and($result['width'])->toBe(64)
        ->and($result['height'])->toBe(64);
});

it('extracts EXIF including GPS from an image that carries it', function (): void {
    if (! function_exists('exif_read_data') || ! extension_loaded('exif')) {
        $this->markTestSkipped('ext-exif not available.');
    }

    $path = __DIR__.'/../../../fixtures/exif-gps.jpg';

    $result = (new DefaultExifExtractor)->extract($path);

    expect($result['width'])->toBe(48)
        ->and($result['height'])->toBe(32)
        ->and($result['exif'])->toBeArray()
        ->and($result['exif'])->toHaveKey('GPSLatitude')
        ->and($result['exif'])->toHaveKey('GPSLongitude')
        ->and($result['exif']['GPSLatitudeRef'])->toBe('N')
        ->and($result['exif']['Make'])->toBe('TestCam');

    // Extracted EXIF must survive a JSON round-trip into the item's json column.
    expect(json_encode($result['exif']))->not->toBeFalse();
});

it('still returns dimensions but skips EXIF when ext-exif is unavailable', function (): void {
    $path = __DIR__.'/../../../fixtures/exif-gps.jpg';

    // Force the ext-exif availability check off to exercise the graceful-skip path.
    $result = (new DefaultExifExtractor(exifAvailable: false))->extract($path);

    expect($result['width'])->toBe(48)
        ->and($result['height'])->toBe(32)
        ->and($result)->not->toHaveKey('exif');
});
