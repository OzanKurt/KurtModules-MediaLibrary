<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Carbon\CarbonInterface;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property int|null $folder_id
 * @property int $storage_id
 * @property string $slug
 * @property string $title
 * @property string|null $alt_text
 * @property string|null $caption
 * @property string|null $description
 * @property string $filename
 * @property string $mime_type
 * @property int $byte_size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $duration_seconds
 * @property float $focal_x
 * @property float $focal_y
 * @property string|null $dominant_color
 * @property array<int, string>|null $palette
 * @property string|null $blurhash
 * @property array<string, mixed>|null $exif
 * @property array<int, string>|null $ai_tags
 * @property string|null $extracted_text
 * @property int $download_count
 * @property int $view_count
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property MediaLibraryStorage|null $storage
 */
class MediaLibraryItem extends Model
{
    /** @use HasFactory<MediaLibraryItemFactory> */
    use HasFactory;

    use HasTranslations;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'media_library_items';

    /** @var list<string> */
    public array $translatable = ['title', 'alt_text', 'caption', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'folder_id',
        'storage_id',
        'slug',
        'title',
        'alt_text',
        'caption',
        'description',
        'filename',
        'mime_type',
        'byte_size',
        'width',
        'height',
        'duration_seconds',
        'focal_x',
        'focal_y',
        'dominant_color',
        'palette',
        'blurhash',
        'exif',
        'ai_tags',
        'extracted_text',
        'download_count',
        'view_count',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'focal_x' => 'float',
        'focal_y' => 'float',
        'palette' => 'array',
        'exif' => 'array',
        'ai_tags' => 'array',
        'metadata' => 'array',
        'byte_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title', 'onUpdate' => true]];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaLibraryFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<MediaLibraryStorage, $this>
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryStorage::class, 'storage_id');
    }

    /**
     * @return BelongsToMany<MediaLibraryTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaLibraryTag::class, 'media_library_item_tag', 'item_id', 'tag_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MediaLibraryAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaLibraryAttachment::class, 'item_id');
    }

    /**
     * @return HasMany<MediaLibraryVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(MediaLibraryVersion::class, 'item_id');
    }

    /**
     * @return HasMany<MediaLibraryVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaLibraryVariant::class, 'item_id');
    }

    /**
     * @return HasMany<ShareLink, $this>
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class, 'item_id');
    }

    public function spatieMedia(): ?Media
    {
        return $this->storage?->getFirstMedia('mli');
    }

    public function url(?string $conversion = null): string
    {
        $media = $this->spatieMedia();

        if ($media === null) {
            return '';
        }

        return $conversion === null ? $media->getUrl() : $media->getUrl($conversion);
    }

    /**
     * Generate (or fetch from cache) an ad-hoc focal-point-aware variant.
     *
     * @param  array<string, mixed>  $spec
     */
    public function variant(array $spec): MediaLibraryVariant
    {
        return app(VariantGenerator::class)->generateOrFetch($this, $spec);
    }

    /**
     * @return Collection<int, ShareLink>
     */
    public function activeShares(): Collection
    {
        return $this->shareLinks()
            ->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeByOwner(Builder $q, Model $owner): Builder
    {
        return $q->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeByFolder(Builder $q, MediaLibraryFolder $folder, bool $recursive = false): Builder
    {
        if (! $recursive) {
            return $q->where('folder_id', $folder->getKey());
        }

        $prefix = rtrim($folder->path, '/').'/';

        return $q->whereIn('folder_id', function ($sub) use ($folder, $prefix): void {
            $sub->select('id')
                ->from('media_library_folders')
                ->where(function ($w) use ($folder, $prefix): void {
                    $w->where('id', $folder->getKey())
                        ->orWhere('path', 'like', $prefix.'%');
                });
        });
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeByTag(Builder $q, MediaLibraryTag|string $tag): Builder
    {
        return $q->whereHas('tags', function (Builder $rel) use ($tag): void {
            if ($tag instanceof MediaLibraryTag) {
                $rel->whereKey($tag->getKey());

                return;
            }

            $rel->where('media_library_tags.slug', $tag);
        });
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeByMimeType(Builder $q, string $pattern): Builder
    {
        if (! str_contains($pattern, '*')) {
            return $q->where('mime_type', $pattern);
        }

        $like = str_replace('*', '%', $pattern);

        return $q->where('mime_type', 'like', $like);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeByDateRange(Builder $q, CarbonInterface|string|null $from, CarbonInterface|string|null $to): Builder
    {
        if ($from !== null) {
            $q->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $q->where('created_at', '<=', $to);
        }

        return $q;
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $q->where(function (Builder $w) use ($like): void {
            $w->where('title', 'like', $like)
                ->orWhere('alt_text', 'like', $like)
                ->orWhere('caption', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    protected static function newFactory(): MediaLibraryItemFactory
    {
        return MediaLibraryItemFactory::new();
    }
}
