<?php

declare (strict_types=1);
namespace IAWPSCOPED\Brick\Math\Exception;

/**
 * Exception thrown when a number cannot be represented at the requested scale without rounding.
 * @internal
 */
class RoundingNecessaryException extends MathException
{
    /**
     * @psalm-pure
     */
    public static function roundingNecessary() : RoundingNecessaryException
    {
        return new self('Rounding is necessary to represent the result of the operation at this scale.');
    }
}
