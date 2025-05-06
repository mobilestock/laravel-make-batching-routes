<?php

use Illuminate\Auth\Middleware\Authenticate;
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
