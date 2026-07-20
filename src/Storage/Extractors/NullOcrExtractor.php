<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Kurt\Modules\MediaLibrary\Storage\Contracts\OcrExtractor;

/**
 * Safe no-op OCR default. The package ships no OCR engine; bind your own
 * implementation to `media-library.contracts.ocr` to extract text from media.
 * Returning an empty string makes the extraction pipeline skip persistence.
 */
final class NullOcrExtractor implements OcrExtractor
{
    public function extract(string $path): string
    {
        return '';
    }
}
