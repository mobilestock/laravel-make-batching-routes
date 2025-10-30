<?php

namespace MobileStock\MakeBatchingRoutes\Commands;

use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MobileStock\MakeBatchingRoutes\HasBatchingEndpoint;
use MobileStock\MakeBatchingRoutes\Utils\ClassNameSanitize;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MakeBatchingRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:batching-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Factories, api routes and tests to batching';

    private string $projectNamespace;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->projectNamespace = App::getNamespace();
        $this->projectNamespace = rtrim($this->projectNamespace, '\\');

        $modelFiles = $this->getModelList();

        if (empty($modelFiles)) {
            $this->error('Nenhuma model encontrada');
            return;
        }

        Artisan::call('schema:dump');
        $models = [];

        foreach ($modelFiles as $modelFile) {
            $className = $modelFile['className'];
            $model = App::make($className);
            $tableName = $model->getTable();

            $casts = $model->getCasts();
            $enums = array_filter($casts, 'enum_exists');
            $jsons = array_filter($casts, fn(string $type): bool => Str::startsWith($type, ['array', 'json']));

            $hiddenColumns = $model->getHidden();
            $columns = $this->getTableColumnsFromSchema($tableName);
            $columns = array_diff_key($columns, array_flip($hiddenColumns));
            $models[$className] = [
                'name' => $tableName,
                'columns' => array_keys($columns),
                'enums' => array_keys($enums),
                'jsons' => array_keys($jsons),
            ];

            $fields = $this->convertColumnsToFactoryDefinitions($columns);
            $this->insertFactoryFiles($modelFile['fileName'], $fields);
        }

        $this->insertAPIRouteFile($models);
        $this->insertTestFile($models);
        $this->info('Batching routes generated successfully');
    }

    protected function getModelList(): array
    {
        $modelsToGenerate = [];
        $modelPath = App::path('Models');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelPath));

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $class = ClassNameSanitize::sanitizeModel($file);
            if (!class_exists($class)) {
                continue;
            }

            $traits = class_uses_recursive($class);
            if (array_key_exists(HasBatchingEndpoint::class, $traits)) {
                $fileName = Str::before($file->getFilename(), '.php');
                $modelsToGenerate[] = ['className' => $class, 'fileName' => $fileName];
            }
        }

        return $modelsToGenerate;
    }

    protected function getTableColumnsFromSchema(string $tableName): array
    {
        $schemaPath = App::databasePath('schema/mysql-schema.sql');
        if (!File::exists($schemaPath)) {
            throw new DomainException('O arquivo de schema não foi encontrado');
        }

        $sql = File::get($schemaPath);
        if (!preg_match("/CREATE\sTABLE\s`$tableName`\s\((.+?)\)\sENGINE.*/s", $sql, $matches)) {
            throw new DomainException('Tabela não encontrada');
        }

        $columnsSql = $matches[1];
        $lines = explode(PHP_EOL, $columnsSql);
        $columns = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s+`([^`]+)`\s+([a-zA-Z0-9_]+(?:\([^)]*\))?)/', $line, $columnMatches)) {
                $columns[$columnMatches[1]] = $columnMatches[2];
            }
        }

        return $columns;
    }

    /**
     * @issue: https://github.com/mobilestock/backend/issues/891
     */
    protected function convertColumnsToFactoryDefinitions(array $columns): array
    {
        $fields = [];
        $primaryColumn = current(array_keys($columns));
        foreach ($columns as $columnName => $columnType) {
            $uniqueKey = $columnName === $primaryColumn ? '->unique()' : '';
            if (Str::contains($columnType, ['enum', 'set'])) {
                $fakerString = $this->handleEnumOrSetColumns($columnType, $uniqueKey);
                $fields[] = "'$columnName' => $fakerString,";
                continue;
            }

            preg_match('/\((.+)\)/', $columnType, $matches);
            $fields[] = match (true) {
                Str::contains($columnName, 'avatar') => "'$columnName' => \$this->faker{$uniqueKey}->imageUrl(),",
                Str::contains($columnName, 'phone')
                    => "'$columnName' => \$this->faker{$uniqueKey}->cellphoneNumber(false),",
                Str::contains($columnName, 'document') => "'$columnName' => \$this->faker{$uniqueKey}->document(),",
                Str::contains($columnType, 'tinyint(1)') => "'$columnName' => \$this->faker{$uniqueKey}->boolean(),",
                Str::contains($columnType, ['decimal', 'double', 'float'])
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomFloat(2, 1, 64),",
                Str::contains($columnType, 'char(36)') => "'$columnName' => \$this->faker{$uniqueKey}->uuid(),",
                Str::contains($columnType, 'char') && $matches[1] >= 5
                    => "'$columnName' => \$this->faker{$uniqueKey}->text($matches[1]),",
                Str::contains($columnType, 'char')
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomLetters($matches[1]),",
                Str::contains($columnType, 'text') => "'$columnName' => \$this->faker{$uniqueKey}->text(),",
                Str::contains($columnType, ['timestamp', 'datetime']) => "'$columnName' => now()->toDateTimeString(),",
                Str::contains($columnType, 'polygon') => "'$columnName' => \$this->faker{$uniqueKey}->polygon(),",
                Str::contains($columnType, 'point') => "'$columnName' => \$this->faker{$uniqueKey}->point(),",
                Str::contains($columnType, 'int')
                    => "'$columnName' => \$this->faker{$uniqueKey}->numberBetween(1, 64),",
                default => "'$columnName' => null,",
            };
        }

        return $fields;
    }

    protected function handleEnumOrSetColumns(string $columnType, string $uniqueKey): string
    {
        preg_match('/([a-z]+)\((.+)\)/', $columnType, $matches);
        if (empty($matches)) {
            throw new DomainException('Não foi possível encontrar os valores da coluna');
        }

        $columnValues = $matches[2];
        $fakerString =
            $matches[1] === 'enum'
                ? "\$this->faker{$uniqueKey}->randomElement([$columnValues])"
                : "implode(',', \$this->faker{$uniqueKey}->randomElements([$columnValues]))";

        return $fakerString;
    }

    protected function insertFactoryFiles(string $fileName, array $fields): void
    {
        $factoriesPath = App::databasePath('factories');
        File::ensureDirectoryExists("$factoriesPath/Batching");

        $fields = implode(PHP_EOL . '            ', $fields);
        $baseFactoryContent = <<<PHP
<?php

namespace Database\Factories\Batching;

use Illuminate\Database\Eloquent\Factories\Factory;
use MobileStock\MakeBatchingRoutes\Faker\TypesProvider;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\\$this->projectNamespace\\Models\\$fileName>
 */
class {$fileName}BaseFactory extends Factory
{
    public function definition(): array
    {
        \$this->faker->addProvider(new TypesProvider(\$this->faker));

        return [
            $fields
        ];
    }
}

PHP;

        File::put("$factoriesPath/Batching/{$fileName}BaseFactory.php", $baseFactoryContent);
        if (File::exists("$factoriesPath/{$fileName}Factory.php")) {
            return;
        }

        $factoryContent = <<<PHP
<?php

namespace Database\Factories;

use Database\Factories\Batching\\{$fileName}BaseFactory;

class {$fileName}Factory extends {$fileName}BaseFactory
{
}

PHP;

        File::put("$factoriesPath/{$fileName}Factory.php", $factoryContent);
    }

    protected function insertAPIRouteFile(array $models): void
    {
        $routes = [];
        foreach ($models as $modelClassName => $table) {
            $model = App::make($modelClassName);
            $middlewares = $model::getBatchingMiddlewares();
            $suffix = '';
            if (!empty($middlewares)) {
                $middlewares = array_map(fn(string $middleware): string => "'$middleware'", $middlewares);
                $middlewaresString = implode(', ', $middlewares);
                $suffix = "->middleware([$middlewaresString])";
            }

            $routes[] = "Route::get('/{$table['name']}', [Batching::class, 'find']){$suffix};";
            $routes[] = "Route::get('/grouped/{$table['name']}', [Batching::class, 'findGrouped']){$suffix};";
        }

        $routes = implode(PHP_EOL, $routes);
        $apiFileContent = <<<PHP
<?php

use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;
use Illuminate\Support\Facades\Route;

$routes

PHP;

        $apiPath = App::basePath('routes/batching.php');
        File::put($apiPath, $apiFileContent);
    }

    protected function insertTestFile(array $models): void
    {
        $tests = [];
        foreach ($models as $modelNamespace => $table) {
            $model = App::make($modelNamespace);
            $middlewares = $model::getBatchingMiddlewares();

            $middlewareRemotion = '';
            if (!empty($middlewares)) {
                $middlewares = array_map(function (string $middleware): string {
                    $parts = explode(':', $middleware);
                    $middleware = current($parts);

                    return "'$middleware'";
                }, $middlewares);

                $middlewareRemotion = '->withoutMiddleware([' . implode(', ', $middlewares) . '])';
            }

            $primaryColumn = current($table['columns']);

            $queryParams = [];
            foreach ($table['columns'] as $column) {
                $transformer = match (true) {
                    in_array($column, $table['enums']) => '->pluck(\'value\')',
                    in_array($column, $table['jsons']) => '->map(\'json_encode\')',
                    default => '',
                };

                $queryParams[] = "\$queryParams['$column'] = \$values->pluck('$column'){$transformer}->toArray();";
            }
            $queryParams = implode(PHP_EOL . '    ', $queryParams);

            $tests[] = <<<PHP
it('should retrieves grouped values from {$table['name']}', function () {
    \$values = $modelNamespace::withoutEvents(fn() => $modelNamespace::factory(MODEL_INSTANCES_COUNT)->create());
    $queryParams

    \$query = http_build_query(\$queryParams);
    \$response = \$this{$middlewareRemotion}->get("api/batching/grouped/{$table['name']}?\$query");

    \$sorter = \$queryParams['$primaryColumn'];
    \$values = \$values->groupBy('$primaryColumn');
    \$values = \$values->sortKeysUsing(function (mixed \$a, mixed \$b) use (\$sorter): int {
        \$indexA = array_search(\$a, \$sorter);
        \$indexB = array_search(\$b, \$sorter);

        return \$indexA <=> \$indexB;
    });
    \$expected = \$values->values()->toArray();

    \$response->assertOk();
    \$response->assertJson(\$expected);
});

it('should retrieves all values from {$table['name']} with controller sorting', function () {
    \$values = $modelNamespace::withoutEvents(fn() => $modelNamespace::factory(MODEL_INSTANCES_COUNT)->create());
    $queryParams
    \$queryParams['order_by_field'] = '$primaryColumn';

    \$query = http_build_query(\$queryParams);
    \$response = \$this{$middlewareRemotion}->get("api/batching/{$table['name']}?\$query");
    \$response->assertOk();
    \$response->assertJson(\$values->toArray());
});

it('should retrieves all values from {$table['name']} without controller sorting', function () {
    \$values = $modelNamespace::withoutEvents(fn() => $modelNamespace::factory(MODEL_INSTANCES_COUNT)->create());
    \$request = Request::create('api/batching/{$table['name']}');
    Request::swap(\$request);

    \$controller = new Batching();
    \$response = \$controller->find();
    \$expected = \$values->sortBy('$primaryColumn')->values()->toArray();
    expect(\$response)->toBe(\$expected);
});
PHP;
        }

        $testContent = implode(PHP_EOL . PHP_EOL, $tests);
        $testContent = <<<PHP
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use MobileStock\MakeBatchingRoutes\Http\Controllers\Batching;

uses(RefreshDatabase::class);

const MODEL_INSTANCES_COUNT = 3;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
});

$testContent

afterEach(function () {
    Schema::enableForeignKeyConstraints();
});

PHP;

        $testDirectory = 'tests/Feature';
        File::ensureDirectoryExists($testDirectory);
        $testPath = App::basePath("$testDirectory/BatchingControllerTest.php");
        File::put($testPath, $testContent);
    }
}
