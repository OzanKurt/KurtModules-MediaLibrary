<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;

final class SavedSearchPolicy
{
    public function view(Authenticatable $user, MediaLibrarySavedSearch $search): bool
    {
        return $search->user_id === $user->getAuthIdentifier();
    }

    public function delete(Authenticatable $user, MediaLibrarySavedSearch $search): bool
    {
        return $this->view($user, $search);
    }
}
