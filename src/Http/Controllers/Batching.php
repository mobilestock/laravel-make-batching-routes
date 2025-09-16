<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rule;
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

        $requestData = Request::except(['limit', 'page', 'order_by']);
        $paginationOptions = Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order_by' => ['nullable', Rule::in(array_keys($requestData))],
        ]);

        $limit = $paginationOptions['limit'] ?? 1000;
        $page = $paginationOptions['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $query = $model::query()->limit($limit)->offset($offset);
        if (App::environment('testing')) {
            $query->withoutGlobalScopes();
        }

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();
        if (empty($paginationOptions['order_by'])) {
            return $databaseValues;
        }

        $key = $paginationOptions['order_by'];
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
