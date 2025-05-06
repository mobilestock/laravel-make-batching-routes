<?php

namespace MobileStock\MakeBatchingRoutes\Faker;

use Faker\Provider\Base;
use InvalidArgumentException;

class TypesProvider extends Base
{
    public function point(): string
    {
        $latitude = $this->generator->latitude();
        $longitude = $this->generator->longitude();

        return "POINT($longitude $latitude)";
    }

    public function polygon(): string
    {
        $polygon = [];
        for ($i = 0; $i < 3; $i++) {
            $latitude = $this->generator->latitude();
            $longitude = $this->generator->longitude();
            $polygon[] = "$longitude $latitude";
        }

        $polygon[] = $polygon[0];

        return 'POLYGON((' . implode(',', $polygon) . '))';
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
        $parsed = $this->generator->parse($format);
        $document = static::numerify($parsed);

        return $document;
    }
}
