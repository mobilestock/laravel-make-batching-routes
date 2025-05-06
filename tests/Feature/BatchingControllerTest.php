<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use RuntimeException;

$MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () use ($MODEL_PATH) {
    File::ensureDirectoryExists($MODEL_PATH);
});

dataset('datasetControllerFindFails', function () use ($MODEL_PATH) {
    return [
        'file' => ["$MODEL_PATH/FileNotFound.txt", ''],
        'class' => ["$MODEL_PATH/ClassNotFound.php", '<?php namespace Fake\Models; class ClassNotFound {}'],
        'table' => [
            "$MODEL_PATH/TableNotFound.php",
            '<?php namespace Tests\Temp\Models; class TableNotFound extends \Illuminate\Database\Eloquent\Model {}',
        ],
    ];
});

it('should throws exception if no :dataset is found', function (string $filePath, string $fileContent) {
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('path')
        ->with('Models')
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models');
    File::put($filePath, $fileContent);

    $controller = new Batching();
    $controller->find();
})
    ->with('datasetControllerFindFails')
    ->throws(RuntimeException::class, 'Model não encontrada pra tabela: /');

dataset('datasetControllerFindSucceeds', function () {
    return [
        'with' => [
            'parameters' => ['id' => [3, 2, 1], 'order_by' => 'id'],
            'expected' => [['id' => 3], ['id' => 2], ['id' => 1]],
        ],
        'without' => [
            'parameters' => [],
            'expected' => [['id' => 3], ['id' => 1], ['id' => 2]],
        ],
    ];
});

it('should work correctly :dataset sorting', function (array $parameters, array $expected) use ($MODEL_PATH) {
    $request = Request::create('api/batching/tables', parameters: $parameters);
    Request::swap($request);
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->twice()
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('path')
        ->with('Models')
        ->twice()
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models');
    File::put(
        "$MODEL_PATH/Table.php",
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $pdoMock = Mockery::mock(PDO::class);

    $connectionMock = Mockery::mock(Connection::class)->makePartial();
    $connectionMock->__construct($pdoMock);
    $connectionMock->shouldReceive('select')->andReturn([['id' => 3], ['id' => 1], ['id' => 2]]);

    $resolverMock = Mockery::mock(DatabaseManager::class);
    $resolverMock->shouldReceive('connection')->andReturn($connectionMock);

    Model::setConnectionResolver($resolverMock);

    $controller = new Batching();
    $response = $controller->find();
    expect($response)->toBe($expected);
})->with('datasetControllerFindSucceeds');
