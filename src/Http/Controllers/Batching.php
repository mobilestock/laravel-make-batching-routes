<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use MobileStock\MakeBatchingRoutes\Utils\ClassNameSanitize;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Batching
{
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
            throw new NotFoundHttpException("Model não encontrada pra tabela: $routeResource");
        }

        $paginationOptions = Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $paginationOptions['limit'] ?? 1000;
        $page = $paginationOptions['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /** @var \Illuminate\Database\Eloquent\Model $model*/
        $query = $model::query()->limit($limit)->offset($offset);

        $requestData = Request::except(['limit', 'page']);
        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();
        if (empty($requestData)) {
            return $databaseValues;
        }

        $key = current(array_keys($requestData));
        $sorter = current($requestData);
        usort($databaseValues, function (array $a, array $b) use ($key, $sorter): int {
            $indexA = array_search($a[$key], $sorter);
            $indexB = array_search($b[$key], $sorter);
            return $indexA <=> $indexB;
        });

        return $databaseValues;
    }
}
