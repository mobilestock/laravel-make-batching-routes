<?php

use Faker\Factory;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use MobileStock\MakeBatchingRoutes\Faker\TypesProvider;

$REGEXP_COORDINATES = '-?\d+(\.\d+)?';

beforeEach(function () {
    $faker = Factory::create();
    $faker->addProvider(new TypesProvider($faker));
    $this->typesProvider = $faker;
});

it('should generates a valid POINT string', function () use ($REGEXP_COORDINATES) {
    $point = $this->typesProvider->point();
    expect($point)->toMatch("/^POINT\($REGEXP_COORDINATES $REGEXP_COORDINATES\)$/");
});

it('should generates a valid POLYGON string', function () {
    $value = new Expression(
        "ST_GeomFromText('POLYGON((48.38553 80.52454,5.951743 -41.557817,107.240909 -35.581728,48.38553 80.52454))')"
    );
    $dbSpy = DB::spy()->makePartial();
    $dbSpy->shouldReceive('raw')->andReturnUsing(fn() => $value);

    $polygon = $this->typesProvider->polygon();

    $dbSpy->shouldHaveReceived('raw')->once();
    expect($polygon)->toBeInstanceOf(Expression::class);
    expect($polygon)->toBe($value);
});

it('should generates random letters with specific length', function () {
    $letters = $this->typesProvider->randomLetters(5);
    expect($letters)->toHaveLength(5)->toMatch('/^[a-zA-Z]+$/');
});

it('throws exception when generating random letters with invalid length', function () {
    $this->typesProvider->randomLetters(0);
})->throws(InvalidArgumentException::class);

it('should generates a valid document number', function () {
    $document = $this->typesProvider->document();
    expect($document)->toMatch('/^(\d{11}|\d{8}0001\d{2})$/');
});
