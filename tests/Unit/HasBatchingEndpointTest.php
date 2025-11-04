<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Middleware\SubstituteBindings;
use MobileStock\MakeBatchingRoutes\HasBatchingEndpoint;

dataset('datasetMiddlewares', function () {
    return [
        'default' => [
            new class {
                use HasBatchingEndpoint;
            },
            [Authenticate::class],
        ],
        'no' => [
            new class {
                use HasBatchingEndpoint;

                protected static $middlewares = [];
            },
            [],
        ],
        'custom' => [
            new class {
                use HasBatchingEndpoint;

                protected static $middlewares = [SubstituteBindings::class, Authenticate::class . ':web'];
            },
            [SubstituteBindings::class, Authenticate::class . ':web'],
        ],
    ];
});

it('should returns :dataset middlewares', function (object $class, array $expectedMiddlewares) {
    $method = (new ReflectionClass($class))->getMethod('getBatchingMiddlewares');
    $method->setAccessible(true);
    $middlewares = $method->invoke(null);

    expect($middlewares)->toBe($expectedMiddlewares);
})->with('datasetMiddlewares');

dataset('datasetGlobalAccessPermissions', [
    'default' => [
        new class {
            use HasBatchingEndpoint;
        },
        Collection::make(['admin']),
    ],
    'no' => [
        new class {
            use HasBatchingEndpoint;

            protected static $globalAccessPermissions = [];
        },
        Collection::make([]),
    ],
    'custom' => [
        new class {
            use HasBatchingEndpoint;

            protected static $globalAccessPermissions = ['user', 'editor'];
        },
        Collection::make(['user', 'editor']),
    ],
]);

it('should returns :dataset global access permissions', function (object $class, Collection $expectedPermissions) {
    $method = (new ReflectionClass($class))->getMethod('getBatchingGlobalAccessPermissions');
    $method->setAccessible(true);
    $permissions = $method->invoke(null);

    expect($permissions)->toBeInstanceOf(Collection::class);
    expect($permissions->toArray())->toBe($expectedPermissions->toArray());
})->with('datasetGlobalAccessPermissions');
