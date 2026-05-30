<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Concerns\HasMediaLibraryItems;
use Kurt\Modules\MediaLibrary\Concerns\IsMediaLibraryOwner;

it('compiles HasMediaLibraryItems when applied to a stub model', function () {
    $consumer = new class extends Model
    {
        use HasMediaLibraryItems;

        protected $table = 'users';

        public $timestamps = false;

        protected $guarded = [];
    };

    expect(method_exists($consumer, 'mediaItemAttachments'))->toBeTrue();
    expect(method_exists($consumer, 'mediaItems'))->toBeTrue();
    expect(method_exists($consumer, 'attachMediaItem'))->toBeTrue();
    expect(method_exists($consumer, 'detachMediaItem'))->toBeTrue();
    expect(method_exists($consumer, 'coverItem'))->toBeTrue();
    expect(method_exists($consumer, 'socialItem'))->toBeTrue();
});

it('attaches and detaches media items via the trait', function () {
    $consumer = new class extends Model
    {
        use HasMediaLibraryItems;

        protected $table = 'users';

        public $timestamps = false;

        protected $guarded = [];

        public function getMorphClass(): string
        {
            return 'demo_consumer';
        }
    };

    $consumer = $consumer::create(['email' => 'consumer@test.dev']);
    $item = MediaLibraryItem::factory()->create();

    $attachment = $consumer->attachMediaItem($item, role: 'cover');
    expect($attachment->role)->toBe('cover');
    expect($consumer->mediaItems('cover')->count())->toBe(1);

    $consumer->detachMediaItem($item, role: 'cover');
    expect($consumer->mediaItems('cover')->count())->toBe(0);
});

it('compiles IsMediaLibraryOwner when applied to a stub model', function () {
    $owner = new class extends Model
    {
        use IsMediaLibraryOwner;

        protected $table = 'users';

        public $timestamps = false;

        protected $guarded = [];
    };

    expect(method_exists($owner, 'mediaLibraryItems'))->toBeTrue();
    expect(method_exists($owner, 'mediaLibraryFolders'))->toBeTrue();
    expect(method_exists($owner, 'getMediaLibraryDisplayName'))->toBeTrue();
});

it('IsMediaLibraryOwner exposes its mediaLibraryItems/folders relations', function () {
    $ownerClass = new class extends Model
    {
        use IsMediaLibraryOwner;

        protected $table = 'users';

        public $timestamps = false;

        protected $guarded = [];

        public function getMorphClass(): string
        {
            return 'demo_owner';
        }
    };

    $owner = $ownerClass::create(['email' => 'me@test.dev']);

    MediaLibraryItem::factory()->create([
        'owner_type' => 'demo_owner',
        'owner_id' => $owner->getKey(),
    ]);
    MediaLibraryFolder::factory()->create([
        'owner_type' => 'demo_owner',
        'owner_id' => $owner->getKey(),
    ]);

    expect($owner->mediaLibraryItems()->count())->toBe(1);
    expect($owner->mediaLibraryFolders()->count())->toBe(1);
});

it('getMediaLibraryDisplayName falls back to name -> email -> key', function () {
    $owner = new class extends Model
    {
        use IsMediaLibraryOwner;

        protected $table = 'users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $row = $owner::create(['name' => 'Marketing', 'email' => 'm@test.dev']);
    expect($row->getMediaLibraryDisplayName())->toBe('Marketing');

    $nameless = $owner::create(['email' => 'other@test.dev']);
    expect($nameless->getMediaLibraryDisplayName())->toBe('other@test.dev');
});
