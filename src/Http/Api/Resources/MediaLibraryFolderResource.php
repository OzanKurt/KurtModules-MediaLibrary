<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Http\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

/**
 * @mixin MediaLibraryFolder
 */
final class MediaLibraryFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'path' => $this->path,
            'depth' => $this->depth,
            'position' => $this->position,
            'visibility' => $this->visibility->value,
            'item_count' => $this->item_count,
            'descendant_count' => $this->descendant_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
