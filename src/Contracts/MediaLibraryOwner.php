<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Contracts;

interface MediaLibraryOwner
{
    public function getKey(): int|string;

    public function getMorphClass(): string;

    public function getMediaLibraryDisplayName(): string;
}
