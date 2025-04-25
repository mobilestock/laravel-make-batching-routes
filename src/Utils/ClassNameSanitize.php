<?php

namespace MobileStock\MakeBatchingRoutes\Utils;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use SplFileInfo;

class ClassNameSanitize
{
    public static function sanitizeModel(SplFileInfo $file): string
    {
        $modelPath = App::path('Models');
        $namespace = App::getNamespace();
        $namespace = rtrim($namespace, '\\');

        $path = $file->getRealPath();
        $relativePath = Str::after($path, $modelPath . DIRECTORY_SEPARATOR);
        $className = Str::replace(['/', '.php'], ['\\', ''], $relativePath);
        $className = "\\$namespace\\Models\\" . Str::studly($className);

        return $className;
    }
}
