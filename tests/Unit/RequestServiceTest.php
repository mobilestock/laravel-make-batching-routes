<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

$MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () use ($MODEL_PATH) {
    File::ensureDirectoryExists($MODEL_PATH);
});

dataset('findModelsFailCaseProvider', [
    'file to default' => ["$MODEL_PATH/FileNotFound.txt", '', 'find'],
    'class to default' => [
        "$MODEL_PATH/ClassNotFound.php",
        '<?php namespace Fake\Models; class ClassNotFound {}',
        'find',
    ],
    'table to default' => [
        "$MODEL_PATH/TableNotFound.php",
        '<?php namespace Tests\Temp\Models; class TableNotFound extends \Illuminate\Database\Eloquent\Model {}',
        'find',
    ],
]);

it('should throws exception if no :dataset is found', function (string $filePath, string $fileContent) {
    $appSpy = App::spy()->makePartial();
    $appSpy->shouldReceive('getNamespace')->andReturn('Tests\\Temp\\');
    $appSpy->shouldReceive('path')->andReturn('/laravel-make-batching-routes/tests/Temp/Models');

    File::put($filePath, $fileContent);

    $service = new RequestService();
    $service->getRouteModel(false);
})
    ->with('findModelsFailCaseProvider')
    ->throws(RuntimeException::class, 'Model não encontrada pra tabela: /');

it('should return the correct model based on the request uri', function () use ($MODEL_PATH) {
    File::put(
        "$MODEL_PATH/CorrectTable.php",
        <<<PHP
<?php

namespace Tests\Temp\Models;
use Illuminate\Database\Eloquent\Model;

class CorrectTable extends Model {
    protected \$table = 'correct_tables';
}
PHP
    );

    $appSpy = App::spy()->makePartial();
    $appSpy->shouldReceive('getNamespace')->andReturn('Tests\\Temp\\');
    $appSpy->shouldReceive('path')->andReturn('/laravel-make-batching-routes/tests/Temp/Models');

    $request = Request::create('api/batching/grouped/correct_tables');
    Request::swap($request);

    $service = new RequestService();
    $model = $service->getRouteModel(true);

    expect($model)->toBeInstanceOf('\Tests\Temp\Models\CorrectTable');

    $appSpy->shouldHaveReceived('getNamespace')->twice();
    $appSpy->shouldHaveReceived('path')->twice()->with('Models');
    $appSpy->shouldHaveReceived('make')->with('\Tests\Temp\Models\CorrectTable')->once();
});

it('should return false when no header is present', function () {
    $modelSpy = Mockery::spy(Model::class);

    $service = new RequestService();
    $result = $service->shouldIgnoreModelScopes($modelSpy);

    expect($result)->toBeFalse();

    $modelSpy->shouldNotHaveReceived('getBatchingGlobalAccessPermissions');
});

it('should return true when header is present and user has permission', function () {
    $permission = 'ignore-batching-scopes';

    $modelSpy = Mockery::spy(Model::class);
    $modelSpy->shouldReceive('getBatchingGlobalAccessPermissions')->andReturn(Collection::make([$permission]));

    Auth::shouldReceive('shouldUse')->with($permission)->once();

    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnTrue();

    $request = Request::create('api/batching/some_table');
    $request->headers->set('X-Ignore-Scopes', 'true');
    Request::swap($request);

    $service = new RequestService();
    $result = $service->shouldIgnoreModelScopes($modelSpy);

    expect($result)->toBeTrue();

    $modelSpy->shouldHaveReceived('getBatchingGlobalAccessPermissions')->once();
    $gateSpy->shouldHaveReceived('allows')->with($permission)->once();
});
