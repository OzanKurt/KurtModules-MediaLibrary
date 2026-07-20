<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Sharing;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    /** @var class-string<ShareLink> */
    protected $model = ShareLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

        return [
            'item_id' => MediaLibraryItem::factory(),
            'folder_id' => null,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'abilities' => ['view'],
            'invitee_email' => null,
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'access_count' => 0,
            'last_accessed_at' => null,
            'last_accessed_ip' => null,
            'created_by' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function noExpiry(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }

    public function forFolder(int $folderId): static
    {
        return $this->state(fn () => [
            'item_id' => null,
            'folder_id' => $folderId,
        ]);
    }

    public function withDownload(): static
    {
        return $this->state(fn () => ['abilities' => ['view', 'download']]);
    }

    public function withToken(string $token): static
    {
        return $this->state(fn () => [
            'token' => $token,
            'token_hash' => hash('sha256', $token),
        ]);
    }
}
