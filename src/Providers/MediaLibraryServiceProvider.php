<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Kurt\Modules\Core\Modules\ModuleManifest;
use Kurt\Modules\Core\Providers\PackageServiceProvider;
use Kurt\Modules\Core\Support\ModuleCacheFactory;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Listeners\FlushAclCache;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Observers\FolderPermissionObserver;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderMoved;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderPermissionChanged;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryFolderObserver;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryItemObserver;
use Kurt\Modules\MediaLibrary\Console\Commands\DemoCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpirePendingUploadsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpireSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneAccessLogCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVariantsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVersionsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PurgeSubjectCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\RebuildPathsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\RecountCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ReextractCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ReindexCommand;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryFolderPolicy;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryItemPolicy;
use Kurt\Modules\MediaLibrary\Policies\SavedSearchPolicy;
use Kurt\Modules\MediaLibrary\Policies\ShareLinkPolicy;
use Kurt\Modules\MediaLibrary\Search\Contracts\ScoutAdapter;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;
use Kurt\Modules\MediaLibrary\Storage\Contracts\AiTagger;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\OcrExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ConversionEngine;
use Kurt\Modules\MediaLibrary\Storage\Support\FocalPointCropper;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataPipeline;
use Kurt\Modules\MediaLibrary\Storage\Support\ReplaceCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Spatie\LaravelPackageTools\Package;

final class MediaLibraryServiceProvider extends PackageServiceProvider
{
    protected function module(): string
    {
        return 'media-library';
    }

    protected function moduleManifest(): ModuleManifest
    {
        return ModuleManifest::make('media-library')
            ->name('Media Library')
            ->description('WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id, share links, folder ACL.');
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-media-library')
            ->hasConfigFile('media-library')
            ->hasTranslations()
            ->hasViews('media-library')
            ->discoversMigrations()
            ->hasCommands([
                PruneVersionsCommand::class,
                PruneVariantsCommand::class,
                RebuildPathsCommand::class,
                RecountCommand::class,
                ExpireSharesCommand::class,
                PruneSharesCommand::class,
                PruneAccessLogCommand::class,
                PurgeSubjectCommand::class,
                ExpirePendingUploadsCommand::class,
                ReextractCommand::class,
                ReindexCommand::class,
                DemoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Resolver contract bindings from config
        $this->app->singleton(MediaSubjectResolver::class, function ($app) {
            $class = config('media-library.subject_resolver', DefaultSubjectResolver::class);
            $class = is_string($class) ? $class : DefaultSubjectResolver::class;

            /** @var MediaSubjectResolver $instance */
            $instance = $app->make($class);

            return $instance;
        });

        // Optional extractors — only bind the ones actually consumed at runtime.
        // blurhash + palette are injected into MetadataExtractor and run on every
        // image upload; that is the only synchronous extraction the package wires.
        $blurhash = config('media-library.contracts.blurhash');
        if (is_string($blurhash) && $blurhash !== '') {
            $this->app->singleton(BlurhashGenerator::class, function ($app) use ($blurhash) {
                /** @var BlurhashGenerator $instance */
                $instance = $app->make($blurhash);

                return $instance;
            });
        }

        $palette = config('media-library.contracts.palette');
        if (is_string($palette) && $palette !== '') {
            $this->app->singleton(PaletteExtractor::class, function ($app) use ($palette) {
                /** @var PaletteExtractor $instance */
                $instance = $app->make($palette);

                return $instance;
            });
        }

        // Async pipeline extractors — bound only when configured to a class.
        // exif ships a real default; ocr / ai_tagger / scout stay unbound (and
        // are skipped by the ExtractMediaMetadata job) until a consumer supplies
        // an engine. Left unbound, MetadataPipeline skips the step gracefully.
        /** @var array<class-string, string> $pipelineContracts */
        $pipelineContracts = [
            ExifExtractor::class => 'exif',
            OcrExtractor::class => 'ocr',
            AiTagger::class => 'ai_tagger',
            ScoutAdapter::class => 'scout',
        ];

        foreach ($pipelineContracts as $contract => $key) {
            $class = config('media-library.contracts.'.$key);

            if (is_string($class) && $class !== '') {
                $this->app->singleton($contract, static fn ($app): object => $app->make($class));
            }
        }

        // Support services
        $this->app->singleton(MetadataPipeline::class);
        $this->app->singleton(FocalPointCropper::class);

        // The resolver carries the cross-request ACL cache (L2). MediaLibraryAccess
        // keeps the per-request memo (L1). The cache honours the `media-library.cache`
        // block and fails closed to live resolution when disabled or erroring.
        $this->app->singleton(FolderPermissionResolver::class, static fn ($app): FolderPermissionResolver => new FolderPermissionResolver(
            $app->make(MediaSubjectResolver::class),
            $app->make(ModuleCacheFactory::class)->generationalFor('media-library'),
        ));
        $this->app->scoped(MediaLibraryAccess::class);
        $this->app->singleton(ShareLinkSigner::class);
        $this->app->singleton(ShareLinkResolver::class);
        $this->app->singleton(AccessLogger::class);
        $this->app->singleton(MetadataExtractor::class);
        $this->app->singleton(UploadCoordinator::class);
        $this->app->singleton(ReplaceCoordinator::class);
        $this->app->singleton(VariantGenerator::class);
        $this->app->singleton(ConversionEngine::class);

        // Top-level facade-service
        $this->app->singleton(MediaLibrary::class);
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        MediaLibraryItem::observe(MediaLibraryItemObserver::class);
        MediaLibraryFolder::observe(MediaLibraryFolderObserver::class);

        // Emit FolderPermissionChanged on every permission grant/edit/revoke so
        // the ACL cache is invalidated no matter which write path touched the row.
        FolderPermission::observe(FolderPermissionObserver::class);

        // Bump the ACL cache scope on the two staleness signals that are not
        // encoded in the cache key: permission changes and folder moves.
        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(FolderPermissionChanged::class, FlushAclCache::class);
        $events->listen(FolderMoved::class, FlushAclCache::class);

        /** @var Gate $gate */
        $gate = $this->app->make(Gate::class);
        $gate->policy(MediaLibraryItem::class, MediaLibraryItemPolicy::class);
        $gate->policy(MediaLibraryFolder::class, MediaLibraryFolderPolicy::class);
        $gate->policy(ShareLink::class, ShareLinkPolicy::class);
        $gate->policy(MediaLibrarySavedSearch::class, SavedSearchPolicy::class);

        // Conditionally load the share route
        if ((bool) config('media-library.routes.share_enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/share.php');
        }

        // Register the Core-kit REST API. A no-op in headless mode; registers
        // the module rate limiter + read/write route group otherwise.
        $this->registerModuleApi(__DIR__.'/../../routes/api.php');

        // Schedule maintenance commands when running in console
        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(ExpirePendingUploadsCommand::class)->everyFiveMinutes();
                $schedule->command(ExpireSharesCommand::class)->hourly();
                $schedule->command(PruneSharesCommand::class)->daily();
                $schedule->command(PruneAccessLogCommand::class)->daily();
                $schedule->command(PruneVersionsCommand::class)->daily();
                $schedule->command(PruneVariantsCommand::class)->daily();
            });
        }
    }
}
