<?php

namespace MobileStock\MakeBatchingRoutes\Commands;

use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MobileStock\MakeBatchingRoutes\HasBatchingFindEndpoint;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

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
    protected $description = 'Factory, controller, api and test generator to batching';

    public string $projectNamespace;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->projectNamespace = App::getNamespace();
        $this->projectNamespace = rtrim($this->projectNamespace, '\\');

        $reflections = $this->getModelsReflections();

        if (empty($reflections)) {
            $this->error('Nenhuma model encontrada');
            return;
        }

        Artisan::call('schema:dump');
        $this->insertController();
        $tables = [];

        foreach ($reflections as $modelReflection) {
            $namespace = $modelReflection->getName();
            $fileName = $modelReflection->getShortName();
            $model = App::make($modelReflection->name);
            $tableName = $model->getTable();

            $hiddenColumns = $model->getHidden();
            $columns = $this->getTableColumnsFromSchema($tableName);
            $columns = array_diff_key($columns, array_flip($hiddenColumns));
            $tables[$namespace] = ['name' => $tableName, 'columns' => array_keys($columns)];

            $fields = $this->convertColumnsToFactoryDefinitions($columns);
            $this->insertFactoryFiles($fileName, $fields);
        }

        $this->insertAPIRouteFile($tables);
        $this->insertTestFile($tables);
        // TODO: Tentar deixar automático na hora que for executar os testes
        // TODO: Documentar no storybook
        // TODO: Analisar de ter uma tarefa separada que implementa a lib nos projetos
    }

    /**
     * @return array<ReflectionClass>
     */
    public function getModelsReflections(): array
    {
        $modelsToGenerate = [];
        $modelPath = App::path('Models');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelPath));

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $relativePath = Str::after($path, $modelPath . DIRECTORY_SEPARATOR);
            $class = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = "\\$this->projectNamespace\\Models\\" . Str::studly($class);

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $traits = trait_uses_recursive($reflection->getName());
            if (array_key_exists(HasBatchingFindEndpoint::class, $traits)) {
                $modelsToGenerate[] = $reflection;
            }
        }

        return $modelsToGenerate;
    }

    public function getTableColumnsFromSchema(string $tableName): array
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
            if (preg_match('/^\s+`([^`]+)`\s+([^\s,]+)/', $line, $columnMatches)) {
                $columns[$columnMatches[1]] = $columnMatches[2];
            }
        }

        return $columns;
    }

    // @issue: https://github.com/mobilestock/backend/issues/891
    public function convertColumnsToFactoryDefinitions(array $columns): array
    {
        $fields = [];
        $primaryColumn = current(array_keys($columns));
        foreach ($columns as $columnName => $columnType) {
            $uniqueKey = $columnName === $primaryColumn ? '->unique()' : '';
            $handleEnumOrSet = function (string $columnType, string $type) use ($uniqueKey): string {
                preg_match("/$type\((.+)\)/i", $columnType, $matches);
                if (empty($matches)) {
                    throw new DomainException("Não foi possível encontrar os valores do $type");
                }

                return $type === 'enum'
                    ? "\$this->faker{$uniqueKey}->randomElement([$matches[1]])"
                    : "\$this->faker{$uniqueKey}->randomElements([$matches[1]])";
            };

            preg_match('/\((.+)\)/', $columnType, $matches);
            $fields[] = match (true) {
                str_contains($columnName, 'avatar') => "'$columnName' => \$this->faker{$uniqueKey}->imageUrl(),",
                str_contains($columnName, 'phone') => "'$columnName' => \$this->faker{$uniqueKey}->phoneNumberBR(),",
                str_contains($columnName, 'document') => "'$columnName' => \$this->faker{$uniqueKey}->document(),",
                str_contains($columnName, 'latitude') => "'$columnName' => \$this->faker{$uniqueKey}->latitude(),",
                str_contains($columnName, 'longitude') => "'$columnName' => \$this->faker{$uniqueKey}->longitude(),",
                str_contains($columnType, 'tinyint(1)') => "'$columnName' => \$this->faker{$uniqueKey}->boolean(),",
                str_contains($columnType, 'int') => "'$columnName' => \$this->faker{$uniqueKey}->numberBetween(1, 64),",
                str_contains($columnType, 'enum') => "'$columnName' => " . $handleEnumOrSet($columnType, 'enum') . ',',
                str_contains($columnType, 'set') => "'$columnName' => " . $handleEnumOrSet($columnType, 'set') . ',',
                Str::contains($columnType, ['decimal', 'double', 'float'])
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomFloat(2, 1, 64),",
                str_contains($columnType, 'char(36)') => "'$columnName' => \$this->faker{$uniqueKey}->uuid(),",
                str_contains($columnType, 'char') && $matches[1] >= 5
                    => "'$columnName' => \$this->faker{$uniqueKey}->text($matches[1]),",
                str_contains($columnType, 'char')
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomLetters($matches[1]),",
                str_contains($columnType, 'text') => "'$columnName' => \$this->faker{$uniqueKey}->text(),",
                Str::contains($columnType, ['timestamp', 'datetime']) => "'$columnName' => now(),",
                str_contains($columnType, 'polygon') => "'$columnName' => \$this->faker{$uniqueKey}->polygon(),",
                str_contains($columnType, 'point') => "'$columnName' => \$this->faker{$uniqueKey}->point(),",
                default => "'$columnName' => null,",
            };
        }

        return $fields;
    }

    public function insertFactoryFiles(string $fileName, array $fields): void
    {
        $factoriesPath = App::databasePath('factories');
        File::ensureDirectoryExists("$factoriesPath/Batching");

        $fields = implode(PHP_EOL . '            ', $fields);
        $baseFactoryContent = <<<PHP
<?php

namespace Database\Factories\Batching;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\\$this->projectNamespace\\Models\\$fileName>
 */
class {$fileName}BaseFactory extends Factory
{
    public function definition(): array
    {
        \$this->faker->addProvider(new \\MobileStock\\MakeBatchingRoutes\\Faker\\TypesProvider(\$this->faker));

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

    public function insertController(): void
    {
        $controllerFile = <<<PHP
<?php

namespace $this->projectNamespace\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Batching
{
    public function find()
    {
        \$uriPath = Request::path();
        \$uriPath = str_replace('api/', '', \$uriPath);

        \$namespace = App::getNamespace();
        \$namespace = rtrim(\$namespace, '\\\\');

        \$modelPath = App::path('Models');
        \$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\$modelPath));
        foreach (\$files as \$file) {
            if (\$file->isDir() || \$file->getExtension() !== 'php') {
                continue;
            }

            \$path = \$file->getRealPath();
            \$relativePath = Str::after(\$path, \$modelPath . DIRECTORY_SEPARATOR);
            \$class = str_replace(['/', '.php'], ['\\\\', ''], \$relativePath);
            \$class = "\\\\\$namespace\\\\Models\\\\" . Str::studly(\$class);

            if (!class_exists(\$class)) {
                continue;
            }

            \$model = App::make(\$class);
            \$tableName = \$model->getTable();
            if (\$tableName === \$uriPath) {
                break;
            }

            \$model = null;
        }

        if (empty(\$model)) {
            throw new NotFoundHttpException("Model não encontrada pra tabela: \$uriPath");
        }

        Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:0'],
        ]);

        \$data = Request::all();
        \$limit = \$data['limit'] ?? 1000;
        \$page = \$data['page'] ?? 0;
        \$offset = \$limit * \$page;

        /** @var \Illuminate\Database\Eloquent\Builder \$query */
        \$query = \$model::query()->limit(\$limit)->offset(\$offset);

        \$data = Arr::except(\$data, ['limit', 'page']);
        foreach (\$data as \$key => \$value) {
            \$query->{is_array(\$value) ? 'whereIn' : 'where'}(\$key, \$value);
        }

        \$values = \$query->get()->toArray();
        \$data = array_filter(\$data, fn(mixed \$item): bool => is_array(\$item));
        if (empty(\$data)) {
            return \$values;
        }

        \$key = current(array_keys(\$data));
        \$sorter = current(\$data);
        usort(\$values, function (array \$a, array \$b) use (\$key, \$sorter): int {
            \$indexA = array_search(\$a[\$key], \$sorter);
            \$indexB = array_search(\$b[\$key], \$sorter);
            return \$indexA <=> \$indexB;
        });

        return \$values;
    }
}

PHP;
        $controllerPath = App::path('Http/Controllers');
        File::put("$controllerPath/Batching.php", $controllerFile);
    }

    public function insertAPIRouteFile(array $tables): void
    {
        $tablesBlock = [];
        foreach ($tables as $modelNamespace => $table) {
            $model = App::make($modelNamespace);
            $middlewares = $model::getBatchingMiddlewares();
            if (empty($middlewares)) {
                $tablesBlock[] = "Route::get('/{$table['name']}', [Batching::class, 'find']);";
                continue;
            }

            $middlewares = array_map(function (string $middleware): string {
                $parts = explode(':', $middleware);
                $parts = array_map(function (string $part): string {
                    $part = ltrim($part, '\\');
                    $class = class_exists("\\$part") ? "\\$part::class" : "'$part'";

                    return $class;
                }, $parts);
                $middleware = implode(" . ':' . ", $parts);

                return $middleware;
            }, $middlewares);

            $middlewaresString = implode(', ', $middlewares);
            $tablesBlock[] = "Route::get('/{$table['name']}', [Batching::class, 'find'])->middleware([$middlewaresString]);";
        }

        $tablesBlock = implode(PHP_EOL, $tablesBlock);
        $apiFileContent = <<<PHP
<?php

use $this->projectNamespace\Http\Controllers\Batching;
use Illuminate\Support\Facades\Route;

$tablesBlock

PHP;

        $apiPath = App::basePath('routes/BatchingApi.php');
        File::put($apiPath, $apiFileContent);
    }

    public function insertTestFile(array $tables): void
    {
        $tests = [];
        foreach ($tables as $modelNamespace => $table) {
            $model = App::make($modelNamespace);
            $middlewares = $model::getBatchingMiddlewares();

            $middlewareRemotion = '';
            if (!empty($middlewares)) {
                $middlewares = array_map(function (string $middleware): string {
                    $parts = explode(':', $middleware);
                    $middleware = '\\' . current($parts) . '::class';

                    return $middleware;
                }, $middlewares);

                $middlewareRemotion = '->withoutMiddleware([' . implode(', ', $middlewares) . '])';
            }

            $indexColumn = current($table['columns']);

            $queryParams = array_map(
                fn(string $column): string => "\$queryParams['$column'] = \$values->pluck('$column')->toArray();",
                $table['columns']
            );
            $queryParams = implode(PHP_EOL . '    ', $queryParams);

            $tests[] = <<<PHP
it('should retrieves all values from the {$table['name']} with sorting', function () {
    \$values = \\$modelNamespace::withoutEvents(fn() => \\$modelNamespace::factory(3)->create());
    $queryParams

    \$query = http_build_query(\$queryParams);
    \$response = \$this{$middlewareRemotion}->get("api/{$table['name']}?\$query");
    \$response->assertStatus(Response::HTTP_OK);
    \$response->assertJson(\$values->toArray());
});

it('should retrieves all values from the {$table['name']} without sorting', function () {
    \$values = \\$modelNamespace::withoutEvents(fn() => \\$modelNamespace::factory(3)->create());
    \$request = Request::create('api/users');
    Request::swap(\$request);

    \$controller = new Batching();
    \$response = \$controller->find();
    \$expected = \$values->sortBy('$indexColumn')->values()->toArray();
    expect(\$response)->toBe(\$expected);
});
PHP;
        }

        $testContent = implode(PHP_EOL . PHP_EOL, $tests);
        $testContent = <<<PHP
<?php

use $this->projectNamespace\Http\Controllers\Batching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('should jump if class does not exist', function () {
    App::partialMock()
        ->shouldReceive('getNamespace')
        ->once()
        ->andReturn('Fake\\\\')
        ->shouldReceive('path')
        ->once()
        ->andReturn('/users-api/app/Models');

    \$controller = new Batching();
    \$controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

$testContent

PHP;

        $testPath = App::basePath('tests/Feature/BatchingControllerTest.php');
        File::put($testPath, $testContent);
    }
}
