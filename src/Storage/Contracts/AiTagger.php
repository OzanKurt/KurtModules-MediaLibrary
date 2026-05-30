<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface AiTagger
{
    /**
     * @return array<int, string>
     */
    public function tag(string $path): array;
}
