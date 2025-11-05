<?php

namespace MobileStock\MakeBatchingRoutes\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use MobileStock\MakeBatchingRoutes\Utils\ClassNameSanitize;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class RequestService
{
    public static function getRouteModel(): Model
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

            /** @var Model $model */
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

        return $model;
    }
}
