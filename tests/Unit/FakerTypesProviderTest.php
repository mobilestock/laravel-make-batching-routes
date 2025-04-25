<?php

use Faker\Factory;
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

it('should generates a valid POLYGON string', function () use ($REGEXP_COORDINATES) {
    $polygon = $this->typesProvider->polygon();
    expect($polygon)->toMatch(
        "/^POLYGON\(\(($REGEXP_COORDINATES $REGEXP_COORDINATES(,$REGEXP_COORDINATES $REGEXP_COORDINATES){2},$REGEXP_COORDINATES $REGEXP_COORDINATES)\)\)$/"
    );
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
