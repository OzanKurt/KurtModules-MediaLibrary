<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Kurt\Modules\MediaLibrary\Storage\Contracts\AiTagger;

/**
 * Safe no-op AI-tagging default. The package ships no vision/tagging engine;
 * bind your own implementation to `media-library.contracts.ai_tagger` to
 * auto-tag media. Returning an empty list makes the pipeline skip persistence.
 */
final class NullAiTagger implements AiTagger
{
    /**
     * @return array<int, string>
     */
    public function tag(string $path): array
    {
        return [];
    }
}
