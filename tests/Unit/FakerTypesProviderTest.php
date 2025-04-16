<?php

use Faker\Factory;
use MobileStock\MakeBatchingRoutes\Faker\TypesProvider;

beforeEach(function () {
    $faker = Factory::create();
    $faker->addProvider(new TypesProvider($faker));
    $this->typesProvider = $faker;
});

it('should generates a valid POINT string', function () {
    $point = $this->typesProvider->point();
    expect($point)->toMatch('/^POINT\(-?\d+(\.\d+)? -?\d+(\.\d+)?\)$/');
});

it('should generates a valid POLYGON string', function () {
    $polygon = $this->typesProvider->polygon();
    expect($polygon)->toMatch(
        '/^POLYGON\(\((-?\d+(\.\d+)? -?\d+(\.\d+)?(,-?\d+(\.\d+)? -?\d+(\.\d+)?){2},-?\d+(\.\d+)? -?\d+(\.\d+)?)\)\)$/'
    );
});

it('should generates random letters with specific length', function () {
    $letters = $this->typesProvider->randomLetters(5);
    expect($letters)->toHaveLength(5)->toMatch('/^[a-zA-Z]+$/');
});

it('throws exception when generating random letters with invalid length', function () {
    $this->typesProvider->randomLetters(0);
})->throws(InvalidArgumentException::class);

it('should generates a valid Brazilian phone number', function () {
    $phoneNumber = $this->typesProvider->phoneNumberBR();
    expect($phoneNumber)->toMatch('/^\d{11}$/');
});

it('should generates a valid document number', function () {
    $document = $this->typesProvider->document();
    expect($document)->toMatch('/^(\d{11}|\d{8}0001\d{2})$/');
});
