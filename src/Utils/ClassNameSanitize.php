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
        $nameSpace = App::getNamespace();
        $nameSpace = rtrim($nameSpace, '\\');

        $path = $file->getRealPath();
        $relativePath = Str::after($path, $modelPath . DIRECTORY_SEPARATOR);
        $className = Str::replace(['/', '.php'], ['\\', ''], $relativePath);
        $className = "\\$nameSpace\\Models\\" . Str::studly($className);

        return $className;
    }
}
