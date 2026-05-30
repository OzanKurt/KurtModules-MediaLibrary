<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use RuntimeException;

final class VariantGenerator
{
    public function __construct(private readonly FocalPointCropper $cropper) {}

    /**
     * Generate (or fetch from cache) an ad-hoc focal-point-aware variant
     * for the given item + spec. The spec is canonicalized into a stable
     * key; calling twice with the same spec returns the same variant and
     * updates last_used_at.
     *
     * @param  array<string, mixed>  $spec
     */
    public function generateOrFetch(MediaLibraryItem $item, array $spec): MediaLibraryVariant
    {
        $key = $this->canonicalizeKey($spec);

        $existing = MediaLibraryVariant::query()
            ->where('item_id', $item->id)
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['last_used_at' => now()])->save();

            return $existing;
        }

        $sourcePath = $item->spatieMedia()?->getPath();

        if ($sourcePath === null) {
            throw new RuntimeException('item has no source media');
        }

        $storage = $item->storage;

        if ($storage === null) {
            throw new RuntimeException('item has no storage host');
        }

        $driver = self::resolveDriver();

        if ($driver === null) {
            throw new RuntimeException('no image driver available');
        }

        $disk = (string) config('media-library.uploads.disk', 'public');
        $format = (string) ($spec['format'] ?? 'jpg');
        $variantRelative = 'media-library/variants/'.$storage->item_uid.'/'.$key.'.'.$format;
        $variantFull = Storage::disk($disk)->path($variantRelative);

        $variantDir = dirname($variantFull);
        if (! is_dir($variantDir)) {
            @mkdir($variantDir, 0755, recursive: true);
        }

        $manager = new ImageManager($driver);
        $img = $manager->read($sourcePath);
        $fit = (string) ($spec['fit'] ?? 'fit');
        $width = (int) ($spec['width'] ?? $img->width());
        $height = (int) ($spec['height'] ?? $img->height());

        if ($fit === 'crop' && $width > 0 && $height > 0) {
            $crop = $this->cropper->compute(
                $img->width(),
                $img->height(),
                $width,
                $height,
                (float) $item->focal_x,
                (float) $item->focal_y,
            );
            $img = $img->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
                ->resize($width, $height);
        } else {
            $img = $img->scaleDown($width > 0 ? $width : null, $height > 0 ? $height : null);
        }

        $quality = (int) ($spec['quality'] ?? 85);
        $img->save($variantFull, $quality);

        $mimeType = mime_content_type($variantFull);
        $byteSize = filesize($variantFull);

        return MediaLibraryVariant::create([
            'item_id' => $item->id,
            'key' => $key,
            'spec' => $spec,
            'path' => $variantRelative,
            'mime_type' => $mimeType !== false ? $mimeType : 'application/octet-stream',
            'byte_size' => $byteSize !== false ? $byteSize : 0,
            'last_used_at' => now(),
            'generated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function canonicalizeKey(array $spec): string
    {
        $width = $spec['width'] ?? 'auto';
        $height = $spec['height'] ?? 'auto';
        $fit = $spec['fit'] ?? 'fit';
        $format = $spec['format'] ?? 'jpg';
        $quality = $spec['quality'] ?? 85;

        return implode('-', [
            (string) $width.'x'.(string) $height,
            (string) $fit,
            (string) $format,
            'q'.(string) $quality,
        ]);
    }

    private static function resolveDriver(): ?DriverInterface
    {
        if (extension_loaded('gd') && class_exists(GdDriver::class)) {
            return new GdDriver;
        }

        if (extension_loaded('imagick') && class_exists(ImagickDriver::class)) {
            return new ImagickDriver;
        }

        return null;
    }
}
