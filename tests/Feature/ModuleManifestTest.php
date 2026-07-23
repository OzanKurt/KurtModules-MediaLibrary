<?php

declare(strict_types=1);

use Kurt\Modules\Core\Contracts\ModuleRegistry;

it('declares its manifest into the registry', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('media-library'))->toBeTrue()
        ->and($registry->get('media-library')->getName())->toBe('Media Library');
});
