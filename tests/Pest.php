<?php

use Illuminate\Support\Str;

uses(Tests\TestCase::class)->in(__DIR__);

function fillPrivateProperty(object &$class, string $propertyName, mixed $value): void
{
    $reflection = new ReflectionClass($class);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($class, $value);
}

function invokeProtectedMethod(object $class, string $methodName, array $parameters = []): mixed
{
    $method = new ReflectionMethod($class, $methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($class, $parameters);
}

pest()->afterEach(function () {
    $tempDirectory = __DIR__ . '/Temp';
    $tempFile = new RecursiveDirectoryIterator($tempDirectory);

    foreach ($tempFile as $file) {
        if ($file->isFile() || Str::endsWith($file->getFilename(), '.')) {
            continue;
        }

        File::deleteDirectory($file->getPathname());
    }
});
