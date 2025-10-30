<?php

namespace MobileStock\MakeBatchingRoutes\Faker;

use Faker\Provider\Base;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TypesProvider extends Base
{
    public function point(): string
    {
        $latitude = $this->generator->latitude();
        $longitude = $this->generator->longitude();

        return "POINT($longitude $latitude)";
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

        return DB::raw("ST_GeomFromText('POLYGON(($points))')");
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
}
