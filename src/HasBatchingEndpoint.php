<?php

namespace MobileStock\MakeBatchingRoutes;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property array<class-string> $middlewares
 * @property array<string> $globalAccessPermissions
 */
trait HasBatchingEndpoint
{
    use HasFactory;

    protected static function getBatchingMiddlewares(): array
    {
        $middlewares = static::$middlewares ?? [Authenticate::class];

        return $middlewares;
    }

    /** @return Collection<string> */
    protected static function getBatchingGlobalAccessPermissions(): Collection
    {
        $permissions = static::$globalAccessPermissions ?? [];
        $permissions = Collection::make($permissions);

        return $permissions;
    }
}
