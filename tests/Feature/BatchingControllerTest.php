<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use MobileStock\MakeBatchingRoutes\Services\RequestService;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

const MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () {
    File::ensureDirectoryExists(MODEL_PATH);
});

dataset('datasetControllerFindSucceeds', function () {
    return [
        'with' => [
            'parameters' => ['id' => [3, 2, 1], 'order_by_field' => 'id'],
            'expected' => [['id' => 3], ['id' => 2], ['id' => 1]],
        ],
        'without' => [
            'parameters' => [],
            'expected' => [['id' => 3], ['id' => 1], ['id' => 2]],
        ],
    ];
});

it('should work correctly :dataset sorting', function (array $parameters, array $expected) {
    File::put(
        MODEL_PATH . '/Table.php',
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $request = Request::create('api/batching/tables', parameters: $parameters);
    Request::swap($request);

    $model = App::make('Tests\Temp\Models\Table');
    $requestServiceSpy = Mockery::spy(RequestService::class);
    $requestServiceSpy->shouldReceive('getRouteModel')->andReturn($model);
    $requestServiceSpy->shouldReceive('shouldIgnoreModelScopes')->andReturnTrue();

    $schemaSpy = Schema::spy();
    $schemaSpy->shouldReceive('getColumnListing')->andReturn(['id']);

    $pdoMock = Mockery::mock(PDO::class);

    $connectionMock = Mockery::mock(Connection::class)->makePartial();
    $connectionMock->__construct($pdoMock);
    $connectionMock->shouldReceive('select')->andReturn($expected);

    $resolverMock = Mockery::mock(DatabaseManager::class);
    $resolverMock->shouldReceive('connection')->andReturn($connectionMock);

    Model::setConnectionResolver($resolverMock);

    $controller = new Batching();
    $response = $controller->find($requestServiceSpy);

    expect($response)->toBe($expected);

    $requestServiceSpy->shouldHaveReceived('getRouteModel')->once();
    $requestServiceSpy->shouldHaveReceived('shouldIgnoreModelScopes')->once();

    $schemaSpy->shouldHaveReceived('getColumnListing')->once();
})->with('datasetControllerFindSucceeds');

it('should throw exception for empty values', function () {
    File::put(
        MODEL_PATH . '/Table.php',
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $request = Request::create('api/batching/tables', parameters: ['id' => []]);
    Request::swap($request);

    $model = App::make('Tests\Temp\Models\Table');
    $requestServiceSpy = Mockery::spy(RequestService::class);
    $requestServiceSpy->shouldReceive('getRouteModel')->andReturn($model);
    $requestServiceSpy->shouldReceive('shouldIgnoreModelScopes')->andReturnTrue();

    $schemaSpy = Schema::spy();
    $schemaSpy->shouldReceive('getColumnListing')->andReturn(['id']);

    $controller = new Batching();
    $controller->find($requestServiceSpy);
})->throws(UnprocessableEntityHttpException::class, "The values for the order_by_field 'id' cannot be empty.");

it('should return grouped values', function () {
    File::put(
        MODEL_PATH . '/Table.php',
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $request = Request::create('api/batching/grouped/tables', parameters: ['id' => [3, 2, 1]]);
    Request::swap($request);

    $model = App::make('Tests\Temp\Models\Table');
    $requestServiceSpy = Mockery::spy(RequestService::class);
    $requestServiceSpy->shouldReceive('getRouteModel')->andReturn($model);
    $requestServiceSpy->shouldReceive('shouldIgnoreModelScopes')->andReturnTrue();

    $pdoMock = Mockery::mock(PDO::class);

    $connectionMock = Mockery::mock(Connection::class)->makePartial();
    $connectionMock->__construct($pdoMock);
    $connectionMock
        ->shouldReceive('select')
        ->andReturn([
            ['id' => 3, 'name' => 'Foo'],
            ['id' => 1, 'name' => 'Foo'],
            ['id' => 2, 'name' => 'Foo'],
            ['id' => 3, 'name' => 'Bar'],
        ]);

    $resolverMock = Mockery::mock(DatabaseManager::class);
    $resolverMock->shouldReceive('connection')->andReturn($connectionMock);

    Model::setConnectionResolver($resolverMock);

    $controller = new Batching();
    $response = $controller->findGrouped($requestServiceSpy);

    expect($response)->toBe([
        [['id' => 3, 'name' => 'Foo'], ['id' => 3, 'name' => 'Bar']],
        [['id' => 2, 'name' => 'Foo']],
        [['id' => 1, 'name' => 'Foo']],
    ]);

    $requestServiceSpy->shouldHaveReceived('getRouteModel')->once();
    $requestServiceSpy->shouldHaveReceived('shouldIgnoreModelScopes')->once();
});
