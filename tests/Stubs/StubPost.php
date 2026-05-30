<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\MediaLibrary\Concerns\HasMediaLibraryItems;

final class StubPost extends Model
{
    use HasMediaLibraryItems;

    protected $table = 'stub_posts';

    protected $guarded = [];

    public $timestamps = true;
}
