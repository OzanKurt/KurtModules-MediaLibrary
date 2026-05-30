<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $role
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryAttachment extends Model
{
    /** @use HasFactory<MediaLibraryAttachmentFactory> */
    use HasFactory;

    protected $table = 'media_library_attachments';

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'attachable_type',
        'attachable_id',
        'role',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<MediaLibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'item_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): MediaLibraryAttachmentFactory
    {
        return MediaLibraryAttachmentFactory::new();
    }
}
