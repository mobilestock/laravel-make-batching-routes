<?php

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;

$MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () use ($MODEL_PATH) {
    File::ensureDirectoryExists($MODEL_PATH);
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

it('should work correctly :dataset sorting', function (array $parameters, array $expected) use ($MODEL_PATH) {
    File::put(
        "$MODEL_PATH/Table.php",
        <<<PHP
<?php

namespace Tests\Temp\Models;

use Illuminate\Database\Eloquent\Collection;

class Table extends \Illuminate\Database\Eloquent\Model {

    /** @return Collection<string> */
    protected static function getBatchingGlobalAccessPermissions(): Collection {
        return Collection::make(['viewer']);
    }
}
PHP
    );

    $model = App::make('Tests\Temp\Models\Table');

    $request = Request::create('api/batching/tables', parameters: $parameters);
    $request->headers->set('X-Ignore-Scopes', 'true');
    Request::swap($request);
    Request::macro('batchingRouteModel', fn() => $model);

    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnTrue();

    $schemaSpy = Schema::spy();
    $schemaSpy->shouldReceive('getColumnListing')->andReturn(['id']);

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

    $gateSpy->shouldHaveReceived('allows')->with('viewer')->once();
})->with('datasetControllerFindSucceeds');

it('should return grouped values', function () use ($MODEL_PATH) {
    File::put(
        "$MODEL_PATH/Table.php",
        '<?php namespace Tests\Temp\Models; class Table extends \Illuminate\Database\Eloquent\Model {}'
    );

    $model = App::make('Tests\Temp\Models\Table');
    $request = Request::create('api/batching/grouped/tables', parameters: ['id' => [3, 2, 1]]);
    $request->headers->set('X-Ignore-Scopes', 'true');
    Request::swap($request);
    Request::macro('batchingRouteModel', fn() => $model);

    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnTrue();

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
    $response = $controller->findGrouped();
    expect($response)->toBe([
        [['id' => 3, 'name' => 'Foo'], ['id' => 3, 'name' => 'Bar']],
        [['id' => 2, 'name' => 'Foo']],
        [['id' => 1, 'name' => 'Foo']],
    ]);

    $gateSpy->shouldHaveReceived('allows')->with('viewer')->once();
});
