<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use MobileStock\MakeBatchingRoutes\MakeBatchingRoutesServiceProvider;
use MobileStock\MakeBatchingRoutes\Commands\MakeBatchingRoutes;

it('should calls boot correctly', function () {
    $apiPath = __DIR__ . '/../Temp/BatchingApi.php';
    File::put($apiPath, '<?php');

    App::partialMock()->shouldReceive('basePath')->with('routes/batching.php')->once()->andReturn($apiPath);
    File::partialMock()->shouldReceive('exists')->with($apiPath)->once()->andReturnTrue();
    Route::partialMock()
        ->shouldReceive('group')
        ->once()
        ->andReturnUsing(fn(array $attributes, Closure $callback): mixed => $callback($attributes));

    Mockery::mock(MakeBatchingRoutesServiceProvider::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('loadRoutesFrom')
        ->once()
        ->with($apiPath)
        ->getMock()
        ->boot();
    File::delete($apiPath);
});

it('should not call boot if the file does not exist', function () {
    File::partialMock()->shouldReceive('exists')->once()->andReturnFalse();

    Mockery::mock(MakeBatchingRoutesServiceProvider::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldNotReceive('loadRoutesFrom')
        ->getMock()
        ->boot();
});

it('should registers commands correctly', function () {
    Mockery::mock(MakeBatchingRoutesServiceProvider::class)
        ->makePartial()
        ->shouldReceive('commands')
        ->with([MakeBatchingRoutes::class])
        ->once()
        ->getMock()
        ->register();
});
