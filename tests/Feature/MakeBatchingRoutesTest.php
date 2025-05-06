<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use MobileStock\MakeBatchingRoutes\Commands\MakeBatchingRoutes;

$BASE_PATH = __DIR__ . '/../Temp';
$DATABASE_PATH = "$BASE_PATH/database";
$MODEL_PATH = "$BASE_PATH/Models";

beforeEach(function () use ($MODEL_PATH, $DATABASE_PATH) {
    foreach ([$MODEL_PATH, $DATABASE_PATH] as $path) {
        File::ensureDirectoryExists($path);
    }

    $this->command = new MakeBatchingRoutes();
});

dataset('datasetNamespaces', function () {
    return [
        'return' => ['Tests\\Temp', 1],
        'not return' => ['', 0],
        'not return without trait' => ['Tests\\Temp\\Models', 0, ''],
    ];
});

it('should :dataset models', function (
    string $namespace,
    int $modelCount,
    string $withTrait = 'use HasBatchingEndpoint;'
) use ($MODEL_PATH) {
    $modelContent = <<<PHP
<?php

namespace Tests\Temp\Models;

use Illuminate\Database\Eloquent\Model;
use MobileStock\MakeBatchingRoutes\HasBatchingEndpoint;

class Test extends Model
{
    $withTrait
}
PHP;

    File::put("$MODEL_PATH/Test.php", $modelContent);
    App::partialMock()
        ->shouldReceive('path')
        ->with('Models')
        ->andReturn('/laravel-make-batching-routes/tests/Temp/Models')
        ->shouldReceive('getNamespace')
        ->andReturn($namespace);

    $models = invokeProtectedMethod($this->command, 'getModelsReflections');
    expect($models)->toBeArray()->toHaveCount($modelCount);
})->with('datasetNamespaces');

it('should return columns from sql schema', function () {
    $schemaContent = <<<SQL
CREATE TABLE `test_table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL;
    File::partialMock()
        ->shouldReceive('exists')
        ->once()
        ->andReturnTrue()
        ->shouldReceive('get')
        ->once()
        ->andReturn($schemaContent);

    $columns = invokeProtectedMethod($this->command, 'getTableColumnsFromSchema', ['test_table']);
    expect($columns)->toMatchArray([
        'id' => 'int(11)',
        'name' => 'varchar(255)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ]);
});

it('should not found schema', function () {
    File::partialMock()->shouldReceive('exists')->once()->andReturnFalse();

    invokeProtectedMethod($this->command, 'getTableColumnsFromSchema', ['not_found']);
})->throws(DomainException::class, 'O arquivo de schema não foi encontrado');

it('should not find table in sql schema', function () {
    File::partialMock()->shouldReceive('exists')->once()->andReturnTrue()->shouldReceive('get')->once()->andReturn('');

    invokeProtectedMethod($this->command, 'getTableColumnsFromSchema', ['not_found']);
})->throws(DomainException::class, 'Tabela não encontrada');

it('should convert columns correctly', function () {
    $convertedColumns = invokeProtectedMethod($this->command, 'convertColumnsToFactoryDefinitions', [
        [
            'id' => 'int(11)',
            'avatar' => 'varchar(255)',
            'phone_number' => 'char(11)',
            'document' => 'char(11)',
            'is_active' => 'tinyint(1)',
            'number' => 'int(11)',
            'status' => "enum('PENDING', 'APPROVED', 'REJECTED')",
            'permissions' => "set('READ', 'WRITE')",
            'note' => 'decimal(10,2)',
            'price' => 'double',
            'cents' => 'float',
            'uuid' => 'char(36)',
            'sku' => 'char(12)',
            'state' => 'char(2)',
            'name' => 'varchar(255)',
            'lorem' => 'text',
            'created_at' => 'timestamp',
            'updated_at' => 'datetime',
            'area' => 'polygon',
            'location' => 'point',
            'foo' => 'whatever',
        ],
    ]);

    expect($convertedColumns)->toMatchArray([
        "'id' => \$this->faker->unique()->numberBetween(1, 64),",
        "'avatar' => \$this->faker->imageUrl(),",
        "'phone_number' => \$this->faker->cellphoneNumber(false),",
        "'document' => \$this->faker->document(),",
        "'is_active' => \$this->faker->boolean(),",
        "'number' => \$this->faker->numberBetween(1, 64),",
        "'status' => \$this->faker->randomElement(['PENDING', 'APPROVED', 'REJECTED']),",
        "'permissions' => \$this->faker->randomElements(['READ', 'WRITE']),",
        "'note' => \$this->faker->randomFloat(2, 1, 64),",
        "'price' => \$this->faker->randomFloat(2, 1, 64),",
        "'cents' => \$this->faker->randomFloat(2, 1, 64),",
        "'uuid' => \$this->faker->uuid(),",
        "'sku' => \$this->faker->text(12),",
        "'state' => \$this->faker->randomLetters(2),",
        "'name' => \$this->faker->text(255),",
        "'lorem' => \$this->faker->text(),",
        "'created_at' => now(),",
        "'updated_at' => now(),",
        "'area' => \$this->faker->polygon(),",
        "'location' => \$this->faker->numberBetween(1, 64),",
        "'foo' => null,",
    ]);
});

dataset('datasetDataTypes', function () {
    return ['enum' => 'enum', 'set' => 'set'];
});

it('should not found :dataset values', function (string $type) {
    invokeProtectedMethod($this->command, 'handleEnumOrSetColumns', ["$type()", '']);
})
    ->with('datasetDataTypes')
    ->throws(DomainException::class, 'Não foi possível encontrar os valores da coluna');

dataset('datasetFactoriesGenerator', function () {
    return [
        'only base' => [1, true],
        'model and base' => [2, false],
    ];
});

it('should create :dataset factory', function (int $putTimesCalled, bool $factoryAlreadyExists) use ($DATABASE_PATH) {
    $directoryPath = "$DATABASE_PATH/factories";

    App::shouldReceive('databasePath')->with('factories')->once()->andReturn($directoryPath);
    File::partialMock()
        ->shouldReceive('ensureDirectoryExists')
        ->with("$directoryPath/Batching")
        ->once()
        ->shouldReceive('put')
        ->times($putTimesCalled)
        ->shouldReceive('exists')
        ->with("$directoryPath/TestFactory.php")
        ->once()
        ->andReturn($factoryAlreadyExists);

    fillPrivateProperty($this->command, 'projectNamespace', 'Tests\\Temp');
    invokeProtectedMethod($this->command, 'insertFactoryFiles', [
        'Test',
        [
            "'id' => \$this->faker->unique()->numberBetween(1, 64),",
            "'name' => \$this->faker->text(255),",
            "'created_at' => now(),",
            "'updated_at' => now(),",
        ],
    ]);
})->with('datasetFactoriesGenerator');

dataset('datasetWithAndWithoutMiddlewares', function () {
    return [
        'with' => [
            '\\Tests\\Temp\\Models\\TestWithMiddlewares',
            'test_with_middlewares',
            [Authenticate::class . ':api', Illuminate\Foundation\Http\Middleware\TrimStrings::class],
        ],
        'without' => ['\\Tests\\Temp\\Models\\TestWithoutMiddlewares', 'test_without_middlewares', []],
    ];
});

it('should insert API route :dataset middlewares correctly', function (
    string $modelNamespace,
    string $tableName,
    array $middlewares
) use ($BASE_PATH) {
    $mockClass = Mockery::mock(new class {})
        ->shouldReceive('getBatchingMiddlewares')
        ->once()
        ->andReturn($middlewares)
        ->getMock();

    App::partialMock()
        ->shouldReceive('make')
        ->with($modelNamespace)
        ->once()
        ->andReturn($mockClass)
        ->shouldReceive('basePath')
        ->with('routes/batching.php')
        ->once();
    File::partialMock()->shouldReceive('put')->once();

    invokeProtectedMethod($this->command, 'insertAPIRouteFile', [
        [$modelNamespace => ['name' => $tableName, 'columns' => ['id', 'name']]],
    ]);
})->with('datasetWithAndWithoutMiddlewares');

it('should insert tests :dataset middlewares correctly', function (
    string $modelNamespace,
    string $tableName,
    array $middlewares
) use ($BASE_PATH) {
    $mockClass = Mockery::mock(new class {})
        ->shouldReceive('getBatchingMiddlewares')
        ->once()
        ->andReturn($middlewares)
        ->getMock();

    App::partialMock()
        ->shouldReceive('make')
        ->with($modelNamespace)
        ->once()
        ->andReturn($mockClass)
        ->shouldReceive('basePath')
        ->with('tests/Feature/BatchingControllerTest.php')
        ->once();
    File::partialMock()->shouldReceive('put')->once();

    fillPrivateProperty($this->command, 'projectNamespace', 'Tests\\Temp');
    invokeProtectedMethod($this->command, 'insertTestFile', [
        [$modelNamespace => ['name' => $tableName, 'columns' => ['id', 'name']]],
    ]);
})->with('datasetWithAndWithoutMiddlewares');

it('should handle the command correctly', function () {
    $tableName = 'test_command_handles';
    $columns = [
        'id' => 'int(11)',
        'name' => 'varchar(255)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
    $fields = [
        "'id' => \$this->faker->unique()->numberBetween(1, 64),",
        "'name' => \$this->faker->text(255),",
        "'created_at' => now(),",
        "'updated_at' => now(),",
    ];
    $models = [
        '\\Tests\\Temp\\Models\\Test' => [
            'name' => $tableName,
            'columns' => ['id', 'name', 'created_at', 'updated_at'],
        ],
    ];

    $mockModel = Mockery::mock(Model::class)
        ->shouldReceive('getTable')
        ->once()
        ->andReturn($tableName)
        ->shouldReceive('getHidden')
        ->once()
        ->andReturn([])
        ->getMock();

    App::partialMock()
        ->shouldReceive('getNamespace')
        ->once()
        ->andReturn('Tests\\Temp\\')
        ->shouldReceive('make')
        ->once()
        ->andReturn($mockModel);

    $artisanMock = Mockery::mock(Kernel::class)
        ->shouldReceive('call')
        ->once()
        ->with('schema:dump')
        ->getMock();
    Artisan::swap($artisanMock);

    Mockery::mock(MakeBatchingRoutes::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getModelsReflections')
        ->once()
        ->andReturn([['className' => '\\Tests\\Temp\\Models\\Test', 'fileName' => 'Test']])
        ->shouldReceive('getTableColumnsFromSchema')
        ->with($tableName)
        ->once()
        ->andReturn($columns)
        ->shouldReceive('convertColumnsToFactoryDefinitions')
        ->with($columns)
        ->once()
        ->andReturn($fields)
        ->shouldReceive('insertFactoryFiles')
        ->with('Test', $fields)
        ->once()
        ->shouldReceive('insertAPIRouteFile')
        ->with($models)
        ->once()
        ->shouldReceive('insertTestFile')
        ->with($models)
        ->once()
        ->shouldReceive('info')
        ->with('Batching routes generated successfully')
        ->once()
        ->getMock()
        ->handle();
});

it('should show error if not found models reflections', function () {
    App::partialMock()->shouldReceive('getNamespace')->once()->andReturn('Tests\\Temp\\');
    Mockery::mock(MakeBatchingRoutes::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getModelsReflections')
        ->once()
        ->andReturn([])
        ->shouldReceive('error')
        ->with('Nenhuma model encontrada')
        ->once()
        ->getMock()
        ->handle();
});
