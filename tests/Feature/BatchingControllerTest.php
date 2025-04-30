<?php

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
        ->andReturn($MODEL_PATH);

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
    File::ensureDirectoryExists($MODEL_PATH);
    File::put("$MODEL_PATH/Fake.php", '<?php namespace Tests\Temp\Models; class Test extends Model {}');

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

afterAll(function () use ($MODEL_PATH) {
    File::deleteDirectory($MODEL_PATH);
});
