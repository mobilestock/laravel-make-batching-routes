<?php

namespace MobileStock\MakeBatchingRoutes;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use ReflectionClass;

/**
 * @property array<class-string> $middlewares
 */
trait HasBatchingFindEndpoint
{
    use HasFactory;

    /**
     * @var array<class-string>
     */
    protected static $defaultMiddlewares = [Authenticate::class];

    protected static function getBatchingMiddlewares(): array
    {
        $middlewares = (new ReflectionClass(static::class))->hasProperty('middlewares')
            ? static::$middlewares
            : static::$defaultMiddlewares;

        return $middlewares;
    }
}
