<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Sharing\ShareLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @property int $id
 * @property int|null $item_id
 * @property int|null $folder_id
 * @property string $token
 * @property string|null $token_hash
 * @property array<int, string> $abilities
 * @property string|null $invitee_email
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int $access_count
 * @property Carbon|null $last_accessed_at
 * @property string|null $last_accessed_ip
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ShareLink extends Model
{
    /** @use HasFactory<ShareLinkFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'media_library_share_links';

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'folder_id',
        'token',
        'token_hash',
        'abilities',
        'invitee_email',
        'expires_at',
        'revoked_at',
        'access_count',
        'last_accessed_at',
        'last_accessed_ip',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    /**
     * @return BelongsTo<MediaLibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<MediaLibraryFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryFolder::class, 'folder_id');
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

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ! $this->isExpired();
    }

    protected static function newFactory(): ShareLinkFactory
    {
        return ShareLinkFactory::new();
    }
}
