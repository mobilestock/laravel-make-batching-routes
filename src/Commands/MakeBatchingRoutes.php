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
            $fileName = $modelReflection->getShortName();
            $model = App::make($modelReflection->name);
            $tableName = $model->getTable();

            $hiddenColumns = $model->getHidden();
            $columns = $this->getTableColumnsFromSchema($tableName);
            $columns = array_diff_key($columns, array_flip($hiddenColumns));
            $tables[$modelReflection->name] = ['name' => $tableName, 'columns' => array_keys($columns)];

            $fields = $this->convertColumnsToFactoryDefinitions($columns);
            $this->insertFactoryFiles($fileName, $fields);
        }

        $this->insertAPIRouteFile($tables);
        $this->insertTestFile($tables);
        $this->info('Batching routes generated successfully');
        // TODO: Documentar no storybook
    }

    /**
     * @return array<ReflectionClass>
     */
    public function getModelsReflections(): array
    {
        $modelsToGenerate = [];
        $modelPath = App::path('Models');
        // TODO: Pesquisar porque disso:
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelPath));

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            // TODO: Criar um Helper pra isso nn ficar duplicado com a model
            $path = $file->getRealPath();
            $relativePath = Str::after($path, $modelPath . DIRECTORY_SEPARATOR);
            $class = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = "\\$this->projectNamespace\\Models\\" . Str::studly($class);

            if (!class_exists($class)) {
                continue;
            }

            $traits = trait_uses_recursive($class);
            if (array_key_exists(HasBatchingFindEndpoint::class, $traits)) {
                $modelsToGenerate[] = new ReflectionClass($class);
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
            $uniqueKey = $columnName === $primaryColumn ? '->unique()' : ''; // TODO: Analisar como o unique funciona
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
                Str::contains($columnName, 'avatar') => "'$columnName' => \$this->faker{$uniqueKey}->imageUrl(),",
                Str::contains($columnName, 'phone') => "'$columnName' => \$this->faker{$uniqueKey}->phoneNumberBR(),",
                Str::contains($columnName, 'document') => "'$columnName' => \$this->faker{$uniqueKey}->document(),",
                Str::contains($columnName, 'latitude') => "'$columnName' => \$this->faker{$uniqueKey}->latitude(),",
                Str::contains($columnName, 'longitude') => "'$columnName' => \$this->faker{$uniqueKey}->longitude(),",
                Str::contains($columnType, 'tinyint(1)') => "'$columnName' => \$this->faker{$uniqueKey}->boolean(),",
                Str::contains($columnType, 'int')
                    => "'$columnName' => \$this->faker{$uniqueKey}->numberBetween(1, 64),",
                Str::contains($columnType, 'enum') => "'$columnName' => " . $handleEnumOrSet($columnType, 'enum') . ',',
                Str::contains($columnType, 'set') => "'$columnName' => " . $handleEnumOrSet($columnType, 'set') . ',',
                Str::contains($columnType, ['decimal', 'double', 'float'])
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomFloat(2, 1, 64),",
                Str::contains($columnType, 'char(36)') => "'$columnName' => \$this->faker{$uniqueKey}->uuid(),",
                Str::contains($columnType, 'char') && $matches[1] >= 5
                    => "'$columnName' => \$this->faker{$uniqueKey}->text($matches[1]),",
                Str::contains($columnType, 'char')
                    => "'$columnName' => \$this->faker{$uniqueKey}->randomLetters($matches[1]),",
                Str::contains($columnType, 'text') => "'$columnName' => \$this->faker{$uniqueKey}->text(),",
                Str::contains($columnType, ['timestamp', 'datetime']) => "'$columnName' => now(),",
                Str::contains($columnType, 'polygon') => "'$columnName' => \$this->faker{$uniqueKey}->polygon(),",
                Str::contains($columnType, 'point') => "'$columnName' => \$this->faker{$uniqueKey}->point(),",
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

    // TODO: Jogar pra lib
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
        \$uriPath = str_replace('api/batching/', '', \$uriPath);

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
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        \$requestData = Request::all();
        \$limit = \$requestData['limit'] ?? 1000;
        \$page = \$requestData['page'] ?? 1;
        \$offset = \$limit * (\$page - 1);

        /** @var \Illuminate\Database\Eloquent\Builder \$query */
        \$query = \$model::query()->limit(\$limit)->offset(\$offset);

        \$requestData = Arr::except(\$requestData, ['limit', 'page']);
        foreach (\$requestData as \$key => \$value) {
            \$query->whereIn(\$key, \$value);
        }

        \$databaseValues = \$query->get()->toArray();
        if (empty(\$requestData)) {
            return \$databaseValues;
        }

        // TODO: Documentar que se você quiser um ordenamento e estiver enviando vários parâmetros, o que deve usar pra ordenar tem que ser o primeiro indice
        \$key = current(array_keys(\$requestData));
        \$sorter = current(\$requestData);
        usort(\$databaseValues, function (array \$a, array \$b) use (\$key, \$sorter): int {
            \$indexA = array_search(\$a[\$key], \$sorter);
            \$indexB = array_search(\$b[\$key], \$sorter);
            return \$indexA <=> \$indexB;
        });

        return \$databaseValues;
    }
}

PHP;
        $controllerPath = App::path('Http/Controllers');
        File::put("$controllerPath/Batching.php", $controllerFile);
    }

    public function insertAPIRouteFile(array $tables): void
    {
        $tablesBlock = [];
        foreach ($tables as $modelClassName => $table) {
            $model = App::make($modelClassName);
            $middlewares = $model::getBatchingMiddlewares();
            if (empty($middlewares)) {
                $tablesBlock[] = "Route::get('/{$table['name']}', [Batching::class, 'find']);";
                continue;
            }

            // TODO: Ver se tem algum método de quote
            $middlewares = array_map(fn(string $middleware) => "'$middleware'", $middlewares);

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

        $apiPath = App::basePath('routes/batching.php');
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
                // TODO: Simplificar, como o de cima
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
    \$response = \$this{$middlewareRemotion}->get("api/batching/{$table['name']}?\$query");
    \$response->assertStatus(Response::HTTP_OK);
    \$response->assertJson(\$values->toArray());
});

it('should retrieves all values from the {$table['name']} without sorting', function () {
    \$values = \\$modelNamespace::withoutEvents(fn() => \\$modelNamespace::factory(3)->create());
    \$request = Request::create('api/batching/users');
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
        ->andReturn('/route-api/app/Models');

    \$controller = new Batching();
    \$controller->find();
})->throws(NotFoundHttpException::class, 'Model não encontrada pra tabela: /');

$testContent

PHP;

        $testPath = App::basePath('tests/Feature/BatchingControllerTest.php');
        File::put($testPath, $testContent);
    }
}
