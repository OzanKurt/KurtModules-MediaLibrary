<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibrarySavedSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaLibrarySavedSearch extends Model
{
    /** @use HasFactory<MediaLibrarySavedSearchFactory> */
    use HasFactory;

    protected $table = 'media_library_saved_searches';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'filters',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'array',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'user_id');
    }

    protected static function newFactory(): MediaLibrarySavedSearchFactory
    {
        return MediaLibrarySavedSearchFactory::new();
    }
}
