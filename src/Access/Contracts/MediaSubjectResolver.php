<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;

interface MediaSubjectResolver
{
    /**
     * @return array<int, Subject>
     */
    public function subjects(?Authenticatable $user): array;

    public function defaultOwner(?Authenticatable $user): MediaLibraryOwner;
}
