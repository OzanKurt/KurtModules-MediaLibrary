<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryStorageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $item_uid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryStorage extends Model implements HasMedia
{
    /** @use HasFactory<MediaLibraryStorageFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $table = 'media_library_storage';

    /** @var list<string> */
    protected $fillable = ['item_uid'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('mli')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        /** @var array<string, array<string, mixed>> $conversions */
        $conversions = (array) config('media-library.conversions', []);

        foreach ($conversions as $name => $spec) {
            $width = (int) ($spec['width'] ?? 0);
            $height = (int) ($spec['height'] ?? 0);
            $conversion = $this->addMediaConversion((string) $name);

            if ($width > 0) {
                $conversion->width($width);
            }

            if ($height > 0) {
                $conversion->height($height);
            }

            if (($spec['fit'] ?? 'fit') === 'crop') {
                $conversion->fit(Fit::Crop, $width > 0 ? $width : null, $height > 0 ? $height : null);
            }
        }
    }

    protected static function newFactory(): MediaLibraryStorageFactory
    {
        return MediaLibraryStorageFactory::new();
    }
}
