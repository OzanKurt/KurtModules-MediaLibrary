<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Sharing\AccessLogEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;

/**
 * @property int $id
 * @property int|null $item_id
 * @property int|null $share_link_id
 * @property int|null $user_id
 * @property AccessAction $action
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccessLogEntry extends Model
{
    /** @use HasFactory<AccessLogEntryFactory> */
    use HasFactory;

    protected $table = 'media_library_access_log';

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'share_link_id',
        'user_id',
        'action',
        'ip',
        'user_agent',
        'occurred_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'action' => AccessAction::class,
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MediaLibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<ShareLink, $this>
     */
    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ShareLink::class, 'share_link_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'user_id');
    }

    protected static function newFactory(): AccessLogEntryFactory
    {
        return AccessLogEntryFactory::new();
    }
}
