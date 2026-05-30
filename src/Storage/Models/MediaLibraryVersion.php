<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Storage\MediaLibraryVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @property int $id
 * @property int $item_id
 * @property int $spatie_media_id
 * @property string $filename
 * @property string $mime_type
 * @property int $byte_size
 * @property string|null $changelog
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryVersion extends Model
{
    /** @use HasFactory<MediaLibraryVersionFactory> */
    use HasFactory;

    protected $table = 'media_library_versions';

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'spatie_media_id',
        'filename',
        'mime_type',
        'byte_size',
        'changelog',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'byte_size' => 'integer',
        'spatie_media_id' => 'integer',
    ];

    /**
     * @return BelongsTo<MediaLibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function creator(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'created_by');
    }

    protected static function newFactory(): MediaLibraryVersionFactory
    {
        return MediaLibraryVersionFactory::new();
    }
}
