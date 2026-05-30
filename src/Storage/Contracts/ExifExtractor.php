<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface ExifExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $path): array;
}
