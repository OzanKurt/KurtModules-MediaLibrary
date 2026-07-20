<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataPipeline;

final class ReextractCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:reextract {item}';

    /** @var string */
    protected $description = 'Re-run synchronous metadata extraction for the given media library item.';

    public function handle(): int
    {
        /** @var MediaLibraryItem $item */
        $item = MediaLibraryItem::query()->findOrFail($this->argument('item'));

        $media = $item->spatieMedia();
        if ($media === null) {
            $this->error('item has no media');

            return self::FAILURE;
        }

        $extracted = app(MetadataExtractor::class)->extractSync(
            $media->getPath(),
            (string) $media->mime_type,
        );

        $item->forceFill([
            'width' => $extracted['width'] ?? $item->width,
            'height' => $extracted['height'] ?? $item->height,
            'blurhash' => $extracted['blurhash'] ?? $item->blurhash,
            'dominant_color' => $extracted['dominant_color'] ?? $item->dominant_color,
            'palette' => $extracted['palette'] ?? $item->palette,
        ])->save();

        // Re-run the async pipeline (exif/GPS, ocr, ai tags, scout) inline.
        app(MetadataPipeline::class)->run($item->refresh());

        $this->info("Reextracted metadata for item #{$item->id}.");

        return self::SUCCESS;
    }
}
