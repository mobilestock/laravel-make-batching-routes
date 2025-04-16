<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Routing\Middleware\SubstituteBindings;
use MobileStock\MakeBatchingRoutes\HasBatchingFindEndpoint;

dataset('datasetMiddlewares', function () {
    return [
        'default' => [
            new class {
                use HasBatchingFindEndpoint;
            },
            [Authenticate::class],
        ],
        'no' => [
            new class {
                use HasBatchingFindEndpoint;

                protected static $middlewares = [];
            },
            [],
        ],
        'custom' => [
            new class {
                use HasBatchingFindEndpoint;

                protected static $middlewares = [
                    SubstituteBindings::class,
                    Authenticate::class . ':web',
                    TrimStrings::class,
                ];
            },
            [SubstituteBindings::class, Authenticate::class . ':web', TrimStrings::class],
        ],
    ];
});

it('should returns :dataset middlewares', function (object $class, array $expectedMiddlewares) {
    $method = (new ReflectionClass($class))->getMethod('getBatchingMiddlewares');
    $method->setAccessible(true);
    $middlewares = $method->invoke(null);

    expect($middlewares)->toBe($expectedMiddlewares);
})->with('datasetMiddlewares');
