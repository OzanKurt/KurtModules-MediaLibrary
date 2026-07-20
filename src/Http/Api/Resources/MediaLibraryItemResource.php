<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Http\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @mixin MediaLibraryItem
 */
final class MediaLibraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'description' => $this->description,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'byte_size' => $this->byte_size,
            'width' => $this->width,
            'height' => $this->height,
            'duration_seconds' => $this->duration_seconds,
            'focal_x' => $this->focal_x,
            'focal_y' => $this->focal_y,
            'dominant_color' => $this->dominant_color,
            'palette' => $this->palette,
            'blurhash' => $this->blurhash,
            'ai_tags' => $this->ai_tags,
            'download_count' => $this->download_count,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
