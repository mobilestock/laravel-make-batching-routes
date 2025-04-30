<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () use ($MODEL_PATH) {
    File::deleteDirectory($MODEL_PATH);
    File::ensureDirectoryExists($MODEL_PATH);
});

it('should jump if file does not exist', function () use ($MODEL_PATH) {
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->once()
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('path')
        ->with('Models')
        ->once()
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models');

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

it('should jump if class does not exist', function () use ($MODEL_PATH) {
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->andReturn('Fake\\')
        ->shouldReceive('path')
        ->with('Models')
        ->andReturn($MODEL_PATH);
    File::put("$MODEL_PATH/NotFindClass.php", '<?php namespace Tests\Temp\Models; class NotFindClass {}');

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

it('should jump if not find the table', function () use ($MODEL_PATH) {
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('path')
        ->with('Models')
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models');
    File::put(
        "$MODEL_PATH/NotFindTable.php",
        '<?php namespace Tests\Temp\Models; class NotFindTable extends \Illuminate\Database\Eloquent\Model {}'
    );

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

dataset('datasetControllerFindWorks', function () {
    return [
        'without sorting' => [[], [['id' => 3], ['id' => 1], ['id' => 2]]],
        'with sorting' => [['id' => [3, 2, 1]], [['id' => 3], ['id' => 2], ['id' => 1]]],
    ];
});

it('should work correctly :dataset', function (array $parameters, array $expected) use ($MODEL_PATH) {
    $request = Request::create('api/batching/find_tables', parameters: $parameters);
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
        "$MODEL_PATH/FindTable.php",
        '<?php namespace Tests\Temp\Models; class FindTable extends \Illuminate\Database\Eloquent\Model {}'
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
})->with('datasetControllerFindWorks');

afterAll(function () use ($MODEL_PATH) {
    File::deleteDirectory($MODEL_PATH);
});
