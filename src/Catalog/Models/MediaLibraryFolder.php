<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $path
 * @property int $depth
 * @property int $position
 * @property Visibility $visibility
 * @property int $item_count
 * @property int $descendant_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class MediaLibraryFolder extends Model
{
    /** @use HasFactory<MediaLibraryFolderFactory> */
    use HasFactory;

    use HasTranslations;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'media_library_folders';

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'parent_id',
        'slug',
        'name',
        'description',
        'path',
        'depth',
        'position',
        'visibility',
        'item_count',
        'descendant_count',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'visibility' => Visibility::class,
        'depth' => 'integer',
        'position' => 'integer',
        'item_count' => 'integer',
        'descendant_count' => 'integer',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaLibraryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MediaLibraryItem::class, 'folder_id');
    }

    protected static function newFactory(): MediaLibraryFolderFactory
    {
        return MediaLibraryFolderFactory::new();
    }
}
