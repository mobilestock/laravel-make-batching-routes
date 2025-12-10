<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use MobileStock\MakeBatchingRoutes\Enum\OrderByEnum;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

const MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () {
    File::ensureDirectoryExists(MODEL_PATH);
});

dataset('datasetControllerFindSucceeds', function () {
    $defaultParameters = [
        'id' => [3, 1, 2],
        'order_by_field' => 'id',
        'without_scopes' => true,
    ];

    return [
        'when the sorting direction is set to ' . OrderByEnum::ASC->name => [
            'parameters' => [...$defaultParameters, 'order_by_direction' => OrderByEnum::ASC->value],
            'expected' => [['id' => 1], ['id' => 2], ['id' => 3]],
        ],
        'when the sorting direction is set to ' . OrderByEnum::DESC->name => [
            'parameters' => [...$defaultParameters, 'order_by_direction' => OrderByEnum::DESC->value],
            'expected' => [['id' => 3], ['id' => 2], ['id' => 1]],
        ],
        'when the sorting direction is set to ' . OrderByEnum::CUSTOM->name => [
            'parameters' => [...$defaultParameters, 'order_by_direction' => OrderByEnum::CUSTOM->value],
            'expected' => [['id' => 3], ['id' => 1], ['id' => 2]],
        ],
        'even when the sorting direction is not defined, as long as the parameters are provided' => [
            'parameters' => $defaultParameters,
            'expected' => [['id' => 3], ['id' => 1], ['id' => 2]],
        ],
        'even when the sorting direction is not defined and without passing the parameters' => [
            'parameters' => ['without_scopes' => true],
            'expected' => [['id' => 1], ['id' => 2], ['id' => 3]],
        ],
    ];
});

it('should function correctly :dataset', function (array $parameters, array $expected) {
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

    $schemaSpy->shouldHaveReceived('getColumnListing')->with('tables')->once();
})->with('datasetControllerFindSucceeds');

it('should throw exception for invalid order_by_field', function () {
    File::put(
        MODEL_PATH . '/Table.php',
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $request = Request::create('api/batching/tables', parameters: ['order_by_field' => 'invalid_field']);
    Request::swap($request);

    $model = App::make('Tests\Temp\Models\Table');
    $requestServiceSpy = Mockery::spy(RequestService::class);
    $requestServiceSpy->shouldReceive('getRouteModel')->andReturn($model);
    $requestServiceSpy->shouldReceive('shouldIgnoreModelScopes')->andReturnTrue();

    $schemaSpy = Schema::spy();
    $schemaSpy->shouldReceive('getColumnListing')->andReturn(['id', 'name']);

    $controller = new Batching();
    $controller->find($requestServiceSpy);
})->throws(
    InvalidArgumentException::class,
    "O campo order_by_field 'invalid_field' não é uma coluna válida na tabela 'tables'."
);

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
