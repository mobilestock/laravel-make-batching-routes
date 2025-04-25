<?php

use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('should jump if class does not exist', function () {
    App::partialMock()->shouldReceive('getNamespace')->once()->andReturn('Fake\\');
    $modelPath = App::path('Models');
    File::ensureDirectoryExists($modelPath);

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

afterAll(function () {
    $modelPath = App::path('Models');
    File::deleteDirectory($modelPath);
});
