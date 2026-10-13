<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

const MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () {
    File::ensureDirectoryExists(MODEL_PATH);
});

dataset('findModelsFailCaseProvider', [
    'file to default' => [MODEL_PATH . '/FileNotFound.txt', '', 'find'],
    'class to default' => [
        MODEL_PATH . '/ClassNotFound.php',
        '<?php namespace Fake\Models; class ClassNotFound {}',
        'find',
    ],
    'table to default' => [
        MODEL_PATH . '/TableNotFound.php',
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

it('should return the correct model based on the request uri', function () {
    File::put(
        MODEL_PATH . '/CorrectTable.php',
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

describe('shouldIgnoreModelScopes', function () {
    beforeEach(function () {
        $this->permission = 'supplier';

        $this->modelSpy = Mockery::spy(Model::class);
        $this->modelSpy
            ->shouldReceive('getGlobalScopes')
            ->andReturn(['logged_user' => fn() => null])
            ->byDefault();
        $this->modelSpy
            ->shouldReceive('getBatchingGlobalAccessPermissions')
            ->andReturn(Collection::make([$this->permission]));

        $this->authSpy = Auth::spy();
        $this->gateSpy = Gate::spy();
        $this->gateSpy->shouldReceive('allows')->andReturnTrue();

        $request = Request::create('api/batching/grouped/some_table');
        $request->headers->set('Authorization', 'Bearer user-access-token');
        Request::swap($request);

        $this->service = new RequestService();
    });

    it('should return true when model has global scopes and user has permission', function () {
        $result = $this->service->shouldIgnoreModelScopes($this->modelSpy);

        expect($result)->toBeTrue();

        $this->authSpy
            ->shouldHaveReceived('shouldUse')
            ->with($this->permission)
            ->once();
        $this->gateSpy
            ->shouldHaveReceived('allows')
            ->with($this->permission)
            ->once();
    });

    it('should return false without checking permissions when model has no global scopes', function () {
        $this->modelSpy->shouldReceive('getGlobalScopes')->andReturn([]);

        $result = $this->service->shouldIgnoreModelScopes($this->modelSpy);

        expect($result)->toBeFalse();

        $this->modelSpy->shouldNotHaveReceived('getBatchingGlobalAccessPermissions');
        $this->authSpy->shouldNotHaveReceived('shouldUse');
        $this->gateSpy->shouldNotHaveReceived('allows');
    });

    it('should return false without checking permissions when request has no bearer token', function () {
        $request = Request::create('api/batching/grouped/some_table');
        Request::swap($request);

        $result = $this->service->shouldIgnoreModelScopes($this->modelSpy);

        expect($result)->toBeFalse();

        $this->modelSpy->shouldNotHaveReceived('getBatchingGlobalAccessPermissions');
        $this->authSpy->shouldNotHaveReceived('shouldUse');
        $this->gateSpy->shouldNotHaveReceived('allows');
    });
});
