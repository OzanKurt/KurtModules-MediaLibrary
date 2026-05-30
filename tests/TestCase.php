<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Tests;

use Kurt\Modules\Core\Providers\CoreServiceProvider;
use Kurt\Modules\Core\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
