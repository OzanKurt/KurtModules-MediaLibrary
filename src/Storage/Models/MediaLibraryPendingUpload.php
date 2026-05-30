<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Storage\MediaLibraryPendingUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $upload_id
 * @property string $filename
 * @property string $mime_type
 * @property int|null $byte_size
 * @property string $driver
 * @property array<string, mixed> $driver_payload
 * @property PendingUploadStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon $expires_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibraryPendingUpload extends Model
{
    /** @use HasFactory<MediaLibraryPendingUploadFactory> */
    use HasFactory;

    protected $table = 'media_library_pending_uploads';

    /** @var list<string> */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'upload_id',
        'filename',
        'mime_type',
        'byte_size',
        'driver',
        'driver_payload',
        'status',
        'completed_at',
        'expires_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'driver_payload' => 'array',
        'status' => PendingUploadStatus::class,
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'byte_size' => 'integer',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): MediaLibraryPendingUploadFactory
    {
        return MediaLibraryPendingUploadFactory::new();
    }
}
