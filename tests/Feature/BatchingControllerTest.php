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
    File::put("$MODEL_PATH/NotFindClass.php", '<?php namespace Tests\Temp\Models; class NotFindClass extends Model {}');

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

it('should jump if not find the table', function () use ($MODEL_PATH) {
    $modelContent = <<<PHP
<?php

namespace Tests\Temp\Models;

use Illuminate\Database\Eloquent\Model;
use MobileStock\MakeBatchingRoutes\HasBatchingEndpoint;

class NotFindTable extends Model
{
    use HasBatchingEndpoint;
}
PHP;

    File::put("$MODEL_PATH/NotFindTable.php", $modelContent);
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('path')
        ->with('Models')
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models');

    $controller = new Batching();
    $controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

afterAll(function () use ($MODEL_PATH) {
    File::deleteDirectory($MODEL_PATH);
});
