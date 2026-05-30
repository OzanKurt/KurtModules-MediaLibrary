<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Storage\Support\FocalPointCropper;

it('centers the crop when focal is (0.5, 0.5)', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(1000, 600, 200, 200, 0.5, 0.5);

    expect($rect['width'])->toBe(600);
    expect($rect['height'])->toBe(600);
    expect($rect['x'])->toBe(200);
    expect($rect['y'])->toBe(0);
});

it('pins the crop to the top-left when focal is (0.0, 0.0)', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(1000, 600, 200, 200, 0.0, 0.0);

    expect($rect['x'])->toBe(0);
    expect($rect['y'])->toBe(0);
});

it('pins the crop to the bottom-right when focal is (1.0, 1.0)', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(1000, 600, 200, 200, 1.0, 1.0);

    // With sourceRatio > targetRatio, cropHeight = sourceHeight, cropWidth = round(sourceHeight * targetRatio) = 600
    // x = source - crop = 1000 - 600 = 400; y = source - crop = 600 - 600 = 0
    expect($rect['width'])->toBe(600);
    expect($rect['height'])->toBe(600);
    expect($rect['x'])->toBe(400);
    expect($rect['y'])->toBe(0);
});

it('keeps cropWidth equal to sourceWidth when source is narrower than target ratio', function (): void {
    $cropper = new FocalPointCropper;

    // Source is 400x800 (tall, ratio 0.5). Target wants 16:9 (1.77). So sourceRatio < targetRatio.
    $rect = $cropper->compute(400, 800, 1600, 900, 0.5, 0.5);

    expect($rect['width'])->toBe(400);
    expect($rect['height'])->toBe((int) round(400 / (1600 / 900))); // 225
});

it('keeps cropHeight equal to sourceHeight when source is wider than target ratio', function (): void {
    $cropper = new FocalPointCropper;

    // Source is 2000x500 (wide, ratio 4). Target wants square. So sourceRatio > targetRatio.
    $rect = $cropper->compute(2000, 500, 200, 200, 0.5, 0.5);

    expect($rect['height'])->toBe(500);
    expect($rect['width'])->toBe(500);
});

it('clamps a negative focal value to zero', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(1000, 600, 200, 200, -0.5, -0.5);

    expect($rect['x'])->toBe(0);
    expect($rect['y'])->toBe(0);
});

it('clamps a focal value greater than one to one', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(1000, 600, 200, 200, 1.5, 1.5);

    expect($rect['x'])->toBe(400);
    expect($rect['y'])->toBe(0);
});

it('never produces a crop with dimensions exceeding the source', function (): void {
    $cropper = new FocalPointCropper;
    $rect = $cropper->compute(100, 100, 5000, 5000, 0.5, 0.5);

    expect($rect['width'])->toBeLessThanOrEqual(100);
    expect($rect['height'])->toBeLessThanOrEqual(100);
});
