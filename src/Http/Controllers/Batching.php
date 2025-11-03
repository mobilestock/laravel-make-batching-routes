<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use MobileStock\MakeBatchingRoutes\Enum\OrderByEnum;
use MobileStock\MakeBatchingRoutes\Utils\ClassNameSanitize;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class Batching
{
    // @issue: https://github.com/mobilestock/backend/issues/1294
    public function find()
    {
        $uriPath = Request::path();
        $routeResource = str_replace('api/batching/', '', $uriPath);

        $namespace = App::getNamespace();
        $namespace = rtrim($namespace, '\\');

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

            $model = App::make($class);
            $tableName = $model->getTable();
            if ($tableName === $routeResource) {
                break;
            }

            $model = null;
        }

        if (empty($model)) {
            throw new RuntimeException("Model não encontrada pra tabela: $routeResource");
        }

        $configsValidation = [
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order_by_field' => ['nullable', 'string'],
            'order_by_direction' => ['nullable', Rule::enum(\MobileStock\MakeBatchingRoutes\Enum\OrderByEnum::class)],
            'without_scopes' => ['nullable', 'boolean'],
        ];
        $requestData = Request::except(array_keys($configsValidation));
        $configs = Request::validate($configsValidation);

        $configs['without_scopes'] ??= false;
        $limit = $configs['limit'] ?? 1000;
        $page = $configs['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $query = $model::query()->limit($limit)->offset($offset);
        if (empty($requestData)) {
            $table = $model->getTable();
            $columns = Schema::getColumnListing($table);
            $order = $configs['order_by_field'] ?? current($columns);
            $direction = $configs['order_by_direction'] ?? OrderByEnum::ASC->value;

            $query->orderBy($order, $direction);
        }
        if ($configs['without_scopes']) {
            $query->withoutGlobalScopes();
        }

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();
        if (empty($requestData) || empty($configs['order_by_field'])) {
            return $databaseValues;
        }

        $key = $configs['order_by_field'];
        $sorter = $requestData[$key];
        usort($databaseValues, function (array $a, array $b) use ($key, $sorter): int {
            $indexA = array_search($a[$key], $sorter);
            $indexB = array_search($b[$key], $sorter);
            return $indexA <=> $indexB;
        });

        return $databaseValues;
    }

    // @issue: https://github.com/mobilestock/backend/issues/1294
    public function findGrouped()
    {
        $uriPath = Request::path();
        $routeResource = str_replace('api/batching/grouped/', '', $uriPath);

        $namespace = App::getNamespace();
        $namespace = rtrim($namespace, '\\');

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

            $model = App::make($class);
            $tableName = $model->getTable();
            if ($tableName === $routeResource) {
                break;
            }

            $model = null;
        }

        if (empty($model)) {
            throw new RuntimeException("Model não encontrada pra tabela: $routeResource");
        }

        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $query = $model::query();
        if (App::environment('testing')) {
            $query->withoutGlobalScopes();
        }
        $requestData = Request::all();

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $key = array_key_first($requestData);
        $databaseValues = $query->get();
        $databaseValues = $databaseValues->groupBy($key);
        $sorter = $requestData[$key];
        $databaseValues = Collection::make($sorter)->mapWithKeys(
            fn(string $key): array => [$key => $databaseValues->get($key, Collection::make())]
        );
        $databaseValues = $databaseValues->sortKeysUsing(function (mixed $a, mixed $b) use ($sorter): int {
            $indexA = array_search($a, $sorter);
            $indexB = array_search($b, $sorter);
            return $indexA <=> $indexB;
        });
        $databaseValues = $databaseValues->values();
        $databaseValues = $databaseValues->toArray();

        return $databaseValues;
    }
}
