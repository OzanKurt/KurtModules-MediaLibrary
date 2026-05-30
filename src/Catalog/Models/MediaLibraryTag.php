<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $slug
 * @property string $name
 * @property string|null $color
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryTag extends Model
{
    /** @use HasFactory<MediaLibraryTagFactory> */
    use HasFactory;

    use HasTranslations;
    use Sluggable;

    protected $table = 'media_library_tags';

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'slug',
        'name',
        'color',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    /**
     * @return BelongsToMany<MediaLibraryItem, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MediaLibraryItem::class, 'media_library_item_tag', 'tag_id', 'item_id')
            ->withTimestamps();
    }

    protected static function newFactory(): MediaLibraryTagFactory
    {
        return MediaLibraryTagFactory::new();
    }
}
