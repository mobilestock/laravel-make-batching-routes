<?php

namespace MobileStock\MakeBatchingRoutes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MobileStock\MakeBatchingRoutes\Commands\MakeBatchingRoutes;

// TODO: composer update antes de enviar a tarefa
class MakeBatchingRoutesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // TODO: Discutir sobre como separar esses provedores
        $apiPath = App::basePath('routes/BatchingApi.php');
        if (File::exists($apiPath)) {
            Route::middleware('api')
                ->prefix('api')
                ->group(function () use ($apiPath) {
                    $this->loadRoutesFrom($apiPath);
                });
        }
    }

    public function register(): void
    {
        $this->commands([MakeBatchingRoutes::class]);
    }
}
