<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Tests;

use Illuminate\Foundation\Application;

/**
 * Base case for the REST API suite. Forces `media-library.http.mode = api`
 * during environment setup — the route group is registered in the provider's
 * packageBooted(), which runs after defineEnvironment but before the test body,
 * so flipping the mode in a beforeEach would be too late.
 *
 * The full `http` block is set (not just `mode`) because the package's
 * mergeConfigFrom shallow-merges the `http` array, and a partial value here
 * would otherwise clobber the prefix / middleware / throttle defaults.
 */
abstract class ApiTestCase extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.http', [
            'mode' => 'api',
            'prefix' => 'api/media',
            'middleware' => ['api'],
            'auth_middleware' => ['auth'],
            'rate_limit' => '60,1',
        ]);
    }
}
