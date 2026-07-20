<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Search\Contracts\ScoutAdapter;
use Kurt\Modules\MediaLibrary\Storage\Contracts\AiTagger;
use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\OcrExtractor;
use Kurt\Modules\MediaLibrary\Storage\Events\AiTagsAssigned;
use Kurt\Modules\MediaLibrary\Storage\Events\ExifExtracted;
use Kurt\Modules\MediaLibrary\Storage\Events\TextExtracted;
use Throwable;

/**
 * Runs the configured extractor steps over an item's stored media and persists
 * the results (EXIF/dimensions, OCR text, AI tags) plus optional search
 * indexing. This is the body of the ExtractMediaMetadata job, extracted so it
 * can be exercised directly in tests and re-run by `media-library:reextract`.
 *
 * Steps are listed in `media-library.extractors.pipeline`; each maps to a
 * contract binding in `media-library.contracts`. A step whose contract is
 * unbound (config value null) is skipped gracefully, so OCR / AI tagging /
 * scout stay no-ops until you point them at a real engine.
 */
final class MetadataPipeline
{
    public function run(MediaLibraryItem $item): void
    {
        $media = $item->spatieMedia();

        if ($media === null) {
            return;
        }

        $path = $media->getPath();

        if (! is_readable($path)) {
            return;
        }

        /** @var array<int, mixed> $steps */
        $steps = (array) config('media-library.extractors.pipeline', []);

        foreach ($steps as $step) {
            match ((string) $step) {
                'exif' => $this->runExif($item, $path),
                'ocr' => $this->runOcr($item, $path),
                'ai_tagger' => $this->runAiTagger($item, $path),
                'scout' => $this->runScout($item),
                default => null,
            };
        }
    }

    private function runExif(MediaLibraryItem $item, string $path): void
    {
        if (! app()->bound(ExifExtractor::class)) {
            return;
        }

        /** @var ExifExtractor $extractor */
        $extractor = app(ExifExtractor::class);
        $result = $extractor->extract($path);

        if ($result === []) {
            return;
        }

        $attributes = [];

        /** @var array<string, mixed>|null $exif */
        $exif = isset($result['exif']) && is_array($result['exif']) ? $result['exif'] : null;

        if ($exif !== null && $exif !== []) {
            $attributes['exif'] = $exif;
        }

        // Backfill dimensions the synchronous (GD) pass could not compute.
        if ($item->width === null && isset($result['width'])) {
            $attributes['width'] = (int) $result['width'];
        }

        if ($item->height === null && isset($result['height'])) {
            $attributes['height'] = (int) $result['height'];
        }

        if ($attributes !== []) {
            $item->forceFill($attributes)->save();
        }

        if ($exif !== null) {
            ExifExtracted::dispatch($item, $exif);
        }
    }

    private function runOcr(MediaLibraryItem $item, string $path): void
    {
        if (! app()->bound(OcrExtractor::class)) {
            return;
        }

        /** @var OcrExtractor $extractor */
        $extractor = app(OcrExtractor::class);

        try {
            $text = $extractor->extract($path);
        } catch (Throwable) {
            return;
        }

        if ($text === '') {
            return;
        }

        $item->forceFill(['extracted_text' => $text])->save();

        TextExtracted::dispatch($item, $text);
    }

    private function runAiTagger(MediaLibraryItem $item, string $path): void
    {
        if (! app()->bound(AiTagger::class)) {
            return;
        }

        /** @var AiTagger $tagger */
        $tagger = app(AiTagger::class);

        try {
            $tags = $tagger->tag($path);
        } catch (Throwable) {
            return;
        }

        $tags = array_values(array_filter($tags, static fn (string $t): bool => $t !== ''));

        if ($tags === []) {
            return;
        }

        $item->forceFill(['ai_tags' => $tags])->save();

        AiTagsAssigned::dispatch($item, $tags);
    }

    private function runScout(MediaLibraryItem $item): void
    {
        if (! app()->bound(ScoutAdapter::class)) {
            return;
        }

        /** @var ScoutAdapter $scout */
        $scout = app(ScoutAdapter::class);

        try {
            $scout->index($item);
        } catch (Throwable) {
            // Indexing failures must not fail the extraction job.
        }
    }
}
