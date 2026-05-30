<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Access;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

/**
 * @extends Factory<FolderPermission>
 */
class FolderPermissionFactory extends Factory
{
    /** @var class-string<FolderPermission> */
    protected $model = FolderPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folder_id' => MediaLibraryFolder::factory(),
            'subject_type' => SubjectType::Everyone,
            'subject_value' => null,
            'capability' => Capability::View,
            'cascade' => true,
        ];
    }

    public function forUser(int|string $userId, Capability $capability = Capability::View): static
    {
        return $this->state(fn () => [
            'subject_type' => SubjectType::User,
            'subject_value' => (string) $userId,
            'capability' => $capability,
        ]);
    }

    public function forRole(string $role, Capability $capability = Capability::View): static
    {
        return $this->state(fn () => [
            'subject_type' => SubjectType::Role,
            'subject_value' => $role,
            'capability' => $capability,
        ]);
    }
}
