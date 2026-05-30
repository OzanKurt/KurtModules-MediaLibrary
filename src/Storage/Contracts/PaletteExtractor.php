<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface PaletteExtractor
{
    /**
     * @return array{dominant: string, palette: array<int, string>}
     */
    public function extract(string $path): array;
}
