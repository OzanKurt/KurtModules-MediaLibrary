<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Models;

use Database\Factories\Kurt\Modules\MediaLibrary\Access\FolderPermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

/**
 * @property int $id
 * @property int $folder_id
 * @property SubjectType $subject_type
 * @property string|null $subject_value
 * @property Capability $capability
 * @property bool $cascade
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FolderPermission extends Model
{
    /** @use HasFactory<FolderPermissionFactory> */
    use HasFactory;

    protected $table = 'media_library_folder_permissions';

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'subject_type',
        'subject_value',
        'capability',
        'cascade',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'subject_type' => SubjectType::class,
        'capability' => Capability::class,
        'cascade' => 'boolean',
    ];

    /**
     * @return BelongsTo<MediaLibraryFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryFolder::class, 'folder_id');
    }

    protected static function newFactory(): FolderPermissionFactory
    {
        return FolderPermissionFactory::new();
    }
}
