<?php

namespace MobileStock\MakeBatchingRoutes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MobileStock\MakeBatchingRoutes\Commands\MakeBatchingRoutes;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

class MakeBatchingRoutesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $apiPath = App::basePath('routes/batching.php');
        if (!File::exists($apiPath)) {
            return;
        }

        Route::middleware('api')
            ->prefix('api/batching')
            ->group(function () use ($apiPath) {
                $this->loadRoutesFrom($apiPath);
            });
    }

    public function register(): void
    {
        $this->commands([MakeBatchingRoutes::class]);

        Request::macro('batchingRouteModel', [RequestService::class, 'getRouteModel']);
        Request::macro('batchingShouldIgnoreModelScopes', [RequestService::class, 'shouldIgnoreModelScopes']);
    }
}
