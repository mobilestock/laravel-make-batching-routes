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

it('should generates a valid POINT string', function () {
    $value = new Expression("ST_GeomFromText('POINT(48.38553 80.52454)')");
    $dbSpy = DB::spy()->makePartial();
    $dbSpy->shouldReceive('raw')->andReturnUsing(fn() => $value);

    $point = $this->typesProvider->point();

    $dbSpy->shouldHaveReceived('raw')->once();
    expect($point)->toBeInstanceOf(Expression::class);
    expect($point)->toBe($value);
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

dataset('signedUnsignedIntegerTypes', function () {
    $exponentValue = [
        'tiny_int' => 2 ** 7,
        'small_int' => 2 ** 15,
        'medium_int' => 2 ** 23,
        'int' => 2 ** 31,
        'big_int' => 2 ** 32,
    ];

    return [
        'signed tinyInt' => ['tinyInt', false, -$exponentValue['tiny_int'], $exponentValue['tiny_int'] - 1],
        'unsigned tinyInt' => ['tinyInt', true, 0, 2 ** 8 - 1],
        'signed smallInt' => ['smallInt', false, -$exponentValue['small_int'], $exponentValue['small_int'] - 1],
        'unsigned smallInt' => ['smallInt', true, 0, 2 ** 16 - 1],
        'signed mediumInt' => ['mediumInt', false, -$exponentValue['medium_int'], $exponentValue['medium_int'] - 1],
        'unsigned mediumInt' => ['mediumInt', true, 0, 2 ** 24 - 1],
        'signed int' => ['int', false, -$exponentValue['int'], $exponentValue['int'] - 1],
        'unsigned int' => ['int', true, 0, $exponentValue['big_int'] - 1],
        'unsigned bigInt' => ['bigInt', true, $exponentValue['big_int'], 2 ** 33],
    ];
});

it('should generate a :dataset value within valid range', function (
    string $method,
    bool $unsigned,
    int $min,
    int $max
) {
    $value = $this->typesProvider->$method(unsigned: $unsigned);

    expect($value)->toBeInt();
    expect($value)->toBeGreaterThanOrEqual($min);
    expect($value)->toBeLessThanOrEqual($max);
})->with('signedUnsignedIntegerTypes');

it('should generate a signed bigInt within reduced negative or positive range', function () {
    $value = $this->typesProvider->bigInt(unsigned: false);

    expect($value)->toBeInt();
    expect($value >= 2 ** 32 || $value <= -(2 ** 32))->toBeTrue();
});

it('should generate a bit value within valid range', function () {
    $value = $this->typesProvider->bit();

    expect($value)->toBeInt();
    expect($value)->toBeGreaterThanOrEqual(0);
    expect($value)->toBeLessThanOrEqual(2 ** 6 - 1);
});

dataset('invalidBitSizes', [
    'zero' => [0],
    'negative' => [-1],
    'exceeds max' => [65],
]);

it('should throw exception for invalid bit size :dataset', function (int $size) {
    $this->typesProvider->bit(size: $size);
})
    ->with('invalidBitSizes')
    ->throws(InvalidArgumentException::class, 'bit() size must be between 1 and 64');
