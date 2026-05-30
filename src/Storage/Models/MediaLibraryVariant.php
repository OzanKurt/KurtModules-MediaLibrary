<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Storage\MediaLibraryVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @property int $id
 * @property int $item_id
 * @property string $key
 * @property array<string, mixed> $spec
 * @property string $path
 * @property string $mime_type
 * @property int $byte_size
 * @property Carbon|null $last_used_at
 * @property Carbon $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryVariant extends Model
{
    /** @use HasFactory<MediaLibraryVariantFactory> */
    use HasFactory;

    protected $table = 'media_library_variants';

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'key',
        'spec',
        'path',
        'mime_type',
        'byte_size',
        'last_used_at',
        'generated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'spec' => 'array',
        'last_used_at' => 'datetime',
        'generated_at' => 'datetime',
        'byte_size' => 'integer',
    ];

    /**
     * @return BelongsTo<MediaLibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'item_id');
    }

    protected static function newFactory(): MediaLibraryVariantFactory
    {
        return MediaLibraryVariantFactory::new();
    }
}
