<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

$MODEL_PATH = __DIR__ . '/../Temp/Models';

beforeEach(function () use ($MODEL_PATH) {
    File::ensureDirectoryExists($MODEL_PATH);
});

dataset('datasetControllerFindFails', function () use ($MODEL_PATH) {
    return [
        'file to default' => ["$MODEL_PATH/FileNotFound.txt", '', 'find'],
        'class to default' => [
            "$MODEL_PATH/ClassNotFound.php",
            '<?php namespace Fake\Models; class ClassNotFound {}',
            'find',
        ],
        'table to default' => [
            "$MODEL_PATH/TableNotFound.php",
            '<?php namespace Tests\Temp\Models; class TableNotFound extends \Illuminate\Database\Eloquent\Model {}',
            'find',
        ],
    ];
});

it('should throws exception if no :dataset is found', function (string $filePath, string $fileContent) {
    $appSpy = App::spy()->makePartial();
    $appSpy->shouldReceive('getNamespace')->andReturn('Tests\\Temp\\');
    $appSpy->shouldReceive('path')->andReturn('/laravel-make-batching-routes/tests/Temp/Models');

    File::put($filePath, $fileContent);

    RequestService::getRouteModel();
})
    ->with('datasetControllerFindFails')
    ->throws(RuntimeException::class, 'Model não encontrada pra tabela: /');
