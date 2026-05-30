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

it('returns an array for a readable image file', function (): void {
    $extractor = new DefaultExifExtractor;
    $path = __DIR__.'/../../../fixtures/test.jpg';

    if (! file_exists($path)) {
        $this->markTestSkipped('GD not available to generate fixture.');
    }

    $result = $extractor->extract($path);

    // GD-generated JPEGs typically have no EXIF data, so empty is OK.
    // What matters is that we get an array and don't blow up.
    expect($result)->toBeArray();
});

it('returns an empty array when path is a directory', function (): void {
    $extractor = new DefaultExifExtractor;

    expect($extractor->extract(__DIR__))->toBe([]);
});
