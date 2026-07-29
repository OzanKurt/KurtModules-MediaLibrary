<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Kurt\Modules\Core\Contracts\ModuleCache;
use Kurt\Modules\Core\Support\GenerationalModuleCache;
use Kurt\Modules\Core\Support\ModuleCacheFactory;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

function aclStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

/**
 * @param  list<string>  $roles
 */
function aclRoleResolver(array &$roles): MediaSubjectResolver
{
    return new class($roles) implements MediaSubjectResolver
    {
        /** @param list<string> $roles */
        public function __construct(private array &$roles) {}

        /** @return array<int, Subject> */
        public function subjects(?Authenticatable $user): array
        {
            $subjects = [new Subject(SubjectType::Everyone, null)];

            if ($user !== null) {
                $subjects[] = new Subject(SubjectType::User, (string) $user->getAuthIdentifier());

                foreach ($this->roles as $role) {
                    $subjects[] = new Subject(SubjectType::Role, $role);
                }
            }

            return $subjects;
        }

        public function defaultOwner(?Authenticatable $user): MediaLibraryOwner
        {
            throw new RuntimeException('not used');
        }
    };
}

function aclGenerationalCache(): GenerationalModuleCache
{
    return app(ModuleCacheFactory::class)->generationalFor('media-library');
}

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('media-library.cache.enabled', true);
    config()->set('media-library.cache.ttl', 300);
});

it('SECURITY: does not serve a revoked capability after the permission-changed bump', function (): void {
    $user = aclStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $permission = FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    $resolver = app(FolderPermissionResolver::class);

    // Warm the cross-request cache with the granted capability.
    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);

    // Revoke: deleting the row fires FolderPermissionChanged → FlushAclCache bump.
    $permission->delete();

    // Next read must reflect the revocation, NOT the stale grant.
    expect($resolver->highestCapability($user, $folder))->toBeNull();
});

it('serves the cached capability across reads until the scope is bumped', function (): void {
    $user = aclStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $permission = FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    $resolver = app(FolderPermissionResolver::class);

    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);

    // Remove the row WITHOUT firing model events (mass delete = no bump). The
    // cached capability is still served — proving the cache is real and that the
    // bump signal is load-bearing for the security test above.
    FolderPermission::query()->whereKey($permission->id)->delete();

    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);
});

it('re-resolves against the new ancestry after a folder move bumps the cache', function (): void {
    $user = aclStubUser(11);
    $root = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $elsewhere = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $child = MediaLibraryFolder::factory()->create([
        'parent_id' => $root->id,
        'visibility' => Visibility::Restricted,
    ]);

    // Cascade grant on the root: the child inherits Download while under root.
    FolderPermission::factory()->create([
        'folder_id' => $root->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::Download,
        'cascade' => true,
    ]);

    $resolver = app(FolderPermissionResolver::class);

    expect($resolver->highestCapability($user, $child))->toBe(Capability::Download);

    // Move the child out from under root: FolderMoved → FlushAclCache bump.
    app(MediaLibrary::class)->moveFolderTo($child, $elsewhere);
    $child->refresh();

    expect($resolver->highestCapability($user, $child))->toBeNull();
});

it('yields a different capability when the role fingerprint changes, with no bump', function (): void {
    $roles = [];
    $resolver = new FolderPermissionResolver(aclRoleResolver($roles), aclGenerationalCache());

    $user = aclStubUser(5);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::Role,
        'subject_value' => 'editor',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    // As an editor the user resolves Download and it is cached under rolesHash(editor).
    $roles = ['editor'];
    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);

    // Losing the editor role changes rolesHash → different key → live miss → denied.
    // No permission/move event fired: role staleness self-invalidates via the key.
    $roles = ['viewer'];
    expect($resolver->highestCapability($user, $folder))->toBeNull();
});

it('resolves live (fail-safe) when caching is disabled, reflecting out-of-band changes', function (): void {
    config()->set('media-library.cache.enabled', false);

    // Build the cache AFTER disabling so it captures enabled = false.
    $resolver = new FolderPermissionResolver(new DefaultSubjectResolver, aclGenerationalCache());

    $user = aclStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $permission = FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);

    // Out-of-band removal (mass delete, no events, no bump). Disabled cache means
    // every read is live, so the change is reflected immediately.
    FolderPermission::query()->whereKey($permission->id)->delete();

    expect($resolver->highestCapability($user, $folder))->toBeNull();
});

it('L1 memo: resolves the live ancestry at most once per subject+folder per request', function (): void {
    // No L2 cache (null) so only the per-request L1 memo can prevent re-resolution.
    $access = new MediaLibraryAccess(new FolderPermissionResolver(new DefaultSubjectResolver));

    $user = aclStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $access->check($user, $folder, Capability::View);
    $afterFirst = count(DB::getQueryLog());

    $access->check($user, $folder, Capability::Download);
    $afterSecond = count(DB::getQueryLog());

    expect($afterFirst)->toBeGreaterThan(0)
        ->and($afterSecond)->toBe($afterFirst);
});

it('fails closed to live resolution when the cache layer throws', function (): void {
    // A GenerationalModuleCache over a ModuleCache whose remember() blows up.
    $throwingStore = new class implements ModuleCache
    {
        public function enabled(): bool
        {
            return true;
        }

        public function remember(string $key, Closure $callback, ?int $ttl = null): mixed
        {
            throw new RuntimeException('cache down');
        }

        public function forget(string $key): void {}
    };

    $throwing = new GenerationalModuleCache($throwingStore);

    $resolver = new FolderPermissionResolver(new DefaultSubjectResolver, $throwing);

    $user = aclStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    // A broken cache must not deny a real grant, nor grant on error — it resolves live.
    expect($resolver->highestCapability($user, $folder))->toBe(Capability::Download);
});
