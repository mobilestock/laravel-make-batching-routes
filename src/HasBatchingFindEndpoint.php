<?php

namespace MobileStock\MakeBatchingRoutes;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property array<class-string> $middlewares
 */
trait HasBatchingFindEndpoint
{
    use HasFactory;

    protected static function getBatchingMiddlewares(): array
    {
        $middlewares = static::$middlewares ?? [Authenticate::class];

        return $middlewares;
    }
}
