<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

final class FocalPointCropper
{
    /**
     * Translate a focal point (0..1, 0..1) + a target aspect ratio into
     * a source-coordinate crop rectangle. The crop is the largest
     * rectangle of the target ratio that fits inside the source, then
     * shifted so the focal point sits as close to the rect's center
     * as possible without exceeding source bounds.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function compute(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        float $focalX,
        float $focalY,
    ): array {
        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);
        $targetWidth = max(1, $targetWidth);
        $targetHeight = max(1, $targetHeight);

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }

        $cropWidth = max(1, min($cropWidth, $sourceWidth));
        $cropHeight = max(1, min($cropHeight, $sourceHeight));

        $focalX = max(0.0, min(1.0, $focalX));
        $focalY = max(0.0, min(1.0, $focalY));

        $cx = (int) round($focalX * $sourceWidth);
        $cy = (int) round($focalY * $sourceHeight);

        $x = max(0, min($sourceWidth - $cropWidth, $cx - intdiv($cropWidth, 2)));
        $y = max(0, min($sourceHeight - $cropHeight, $cy - intdiv($cropHeight, 2)));

        return ['x' => $x, 'y' => $y, 'width' => $cropWidth, 'height' => $cropHeight];
    }
}
