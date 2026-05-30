<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Access\Gate;
use Kurt\Modules\Core\Providers\PackageServiceProvider;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryFolderObserver;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryItemObserver;
use Kurt\Modules\MediaLibrary\Console\Commands\DemoCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpirePendingUploadsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpireSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneSharesCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVariantsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVersionsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\RebuildPathsCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\RecountCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ReextractCommand;
use Kurt\Modules\MediaLibrary\Console\Commands\ReindexCommand;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryFolderPolicy;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryItemPolicy;
use Kurt\Modules\MediaLibrary\Policies\SavedSearchPolicy;
use Kurt\Modules\MediaLibrary\Policies\ShareLinkPolicy;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ConversionEngine;
use Kurt\Modules\MediaLibrary\Storage\Support\FocalPointCropper;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
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

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-media-library')
            ->hasConfigFile('media-library')
            ->hasTranslations()
            ->hasViews('media-library')
            ->hasMigrations([
                'create_media_library_folders_table',
                'create_media_library_storage_table',
                'create_media_library_items_table',
                'create_media_library_tags_table',
                'create_media_library_item_tag_table',
                'create_media_library_attachments_table',
                'create_media_library_saved_searches_table',
                'create_media_library_versions_table',
                'create_media_library_variants_table',
                'create_media_library_pending_uploads_table',
                'create_media_library_share_links_table',
                'create_media_library_access_log_table',
                'create_media_library_folder_permissions_table',
            ])
            ->hasCommands([
                PruneVersionsCommand::class,
                PruneVariantsCommand::class,
                RebuildPathsCommand::class,
                RecountCommand::class,
                ExpireSharesCommand::class,
                PruneSharesCommand::class,
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

        // Optional extractors — only bind when configured
        $exif = config('media-library.contracts.exif');
        if (is_string($exif) && $exif !== '') {
            $this->app->singleton(ExifExtractor::class, function ($app) use ($exif) {
                /** @var ExifExtractor $instance */
                $instance = $app->make($exif);

                return $instance;
            });
        }

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

        // Support services
        $this->app->singleton(FocalPointCropper::class);
        $this->app->singleton(FolderPermissionResolver::class);
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
        MediaLibraryItem::observe(MediaLibraryItemObserver::class);
        MediaLibraryFolder::observe(MediaLibraryFolderObserver::class);

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

        // Schedule maintenance commands when running in console
        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(ExpirePendingUploadsCommand::class)->everyFiveMinutes();
                $schedule->command(ExpireSharesCommand::class)->hourly();
                $schedule->command(PruneSharesCommand::class)->daily();
                $schedule->command(PruneVersionsCommand::class)->daily();
                $schedule->command(PruneVariantsCommand::class)->daily();
            });
        }
    }
}
