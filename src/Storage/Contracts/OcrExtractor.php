<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface OcrExtractor
{
    public function extract(string $path): string;
}
