<?php

namespace IAWP\Utils;

/** @internal */
class Calculations
{
    public static function divide(int|float $numerator, int|float $denominator, int $precision = 0)
    {
        if ($denominator == 0 && $numerator > 0) {
            return 100;
        } elseif ($denominator == 0) {
            return 0;
        }
        return \round($numerator / $denominator, $precision);
    }
    public static function percentage(int|float $numerator, int|float $denominator, int $precision = 0)
    {
        if ($denominator == 0 && $numerator > 0) {
            return 100;
        } elseif ($denominator == 0) {
            return 0;
        }
        return \round($numerator / $denominator * 100, $precision);
    }
}
