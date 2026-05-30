<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\OwnerNotResolved;

final class DefaultSubjectResolver implements MediaSubjectResolver
{
    /**
     * @return array<int, Subject>
     */
    public function subjects(?Authenticatable $user): array
    {
        $subjects = [new Subject(SubjectType::Everyone, null)];

        if ($user !== null) {
            $subjects[] = new Subject(SubjectType::User, (string) $user->getAuthIdentifier());
        }

        return $subjects;
    }

    public function defaultOwner(?Authenticatable $user): MediaLibraryOwner
    {
        if (! $user instanceof MediaLibraryOwner) {
            throw new OwnerNotResolved('Authenticated user does not implement MediaLibraryOwner');
        }

        return $user;
    }
}
