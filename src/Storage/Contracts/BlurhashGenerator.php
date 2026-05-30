<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface BlurhashGenerator
{
    public function generate(string $path): string;
}
