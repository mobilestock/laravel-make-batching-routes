<?php

namespace MobileStock\MakeBatchingRoutes\Faker;

use Faker\Provider\Base;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TypesProvider extends Base
{
    public function point(): Expression
    {
        $latitude = $this->generator->latitude();
        $longitude = $this->generator->longitude();
        $point = DB::raw("ST_GeomFromText('POINT($longitude $latitude)')");

        return $point;
    }

    public function polygon(): Expression
    {
        $points = [];
        for ($i = 0; $i < 3; $i++) {
            $latitude = $this->generator->unique()->latitude();
            $longitude = $this->generator->unique()->longitude();
            $points[] = "$longitude $latitude";
        }

        $points[] = $points[0];
        $points = implode(',', $points);
        $polygonExpression = DB::raw("ST_GeomFromText('POLYGON(($points))')");

        return $polygonExpression;
    }

    public function randomLetters(int $maxLength): string
    {
        if ($maxLength < 1) {
            throw new InvalidArgumentException('randomLetters() can only generate text of at least 1 characters');
        }

        $letters = '';
        for ($i = 0; $i < $maxLength; $i++) {
            $letters .= static::randomLetter();
        }

        return $letters;
    }

    public function document(): string
    {
        $format = static::randomElement(['###########', '########0001##']);
        $document = static::numerify($format);

        return $document;
    }

    public function tinyInt(bool $unsigned = false): int
    {
        $value = $unsigned
            ? $this->generator->numberBetween(0, 2 ** 8 - 1)
            : $this->generator->numberBetween(1, 2 ** 7 - 1);

        return $value;
    }

    public function smallInt(bool $unsigned = false): int
    {
        $value = $unsigned
            ? $this->generator->numberBetween(0, 2 ** 16 - 1)
            : $this->generator->numberBetween(1, 2 ** 15 - 1);

        return $value;
    }

    public function mediumInt(bool $unsigned = false): int
    {
        $value = $unsigned
            ? $this->generator->numberBetween(0, 2 ** 24 - 1)
            : $this->generator->numberBetween(1, 2 ** 23 - 1);

        return $value;
    }

    public function int(bool $unsigned = false): int
    {
        $value = $unsigned
            ? $this->generator->numberBetween(0, 2 ** 32 - 1)
            : $this->generator->numberBetween(1, 2 ** 31 - 1);

        return $value;
    }

    /**
     * Unsigned MySQL bigint (up to 2^64-1) exceeds PHP_INT_MAX on 64-bit systems.
     * Faker's numberBetween() relies on mt_rand() which cannot handle values beyond
     * PHP_INT_MAX. We use a reduced range (2^32 to 2^33) that still generates
     * values clearly distinct from smaller integer types.
     */
    public function bigInt(): int
    {
        $value = $this->generator->numberBetween(2 ** 32, 2 ** 33);

        return $value;
    }

    public function bit(int $size = 6): int
    {
        if ($size < 1 || $size > 64) {
            throw new InvalidArgumentException('bit() size must be between 1 and 64');
        }

        $value = $this->generator->numberBetween(0, 2 ** $size - 1);

        return $value;
    }
}
