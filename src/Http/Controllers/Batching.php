<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Support\Arr;
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
        $uriPath = str_replace('api/batching/', '', $uriPath);

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
            if ($tableName === $uriPath) {
                break;
            }

            $model = null;
        }

        if (empty($model)) {
            throw new NotFoundHttpException("Model não encontrada pra tabela: $uriPath");
        }

        Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requestData = Request::all();
        $limit = $requestData['limit'] ?? 1000;
        $page = $requestData['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $model::query()->limit($limit)->offset($offset);

        $requestData = Arr::except($requestData, ['limit', 'page']);
        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();
        if (empty($requestData)) {
            return $databaseValues;
        }

        // TODO: Documentar que se você quiser um ordenamento e estiver enviando vários parâmetros, o que deve usar pra ordenar tem que ser o primeiro indice
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
