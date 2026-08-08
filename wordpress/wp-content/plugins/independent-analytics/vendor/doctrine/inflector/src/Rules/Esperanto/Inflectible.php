<?php

declare (strict_types=1);
namespace IAWPSCOPED\Doctrine\Inflector\Rules\Esperanto;

use IAWPSCOPED\Doctrine\Inflector\Rules\Pattern;
use IAWPSCOPED\Doctrine\Inflector\Rules\Substitution;
use IAWPSCOPED\Doctrine\Inflector\Rules\Transformation;
use IAWPSCOPED\Doctrine\Inflector\Rules\Word;
/** @internal */
class Inflectible
{
    /** @return Transformation[] */
    public static function getSingular() : iterable
    {
        (yield new Transformation(new Pattern('oj$'), 'o'));
    }
    /** @return Transformation[] */
    public static function getPlural() : iterable
    {
        (yield new Transformation(new Pattern('o$'), 'oj'));
    }
    /** @return Substitution[] */
    public static function getIrregular() : iterable
    {
        (yield new Substitution(new Word(''), new Word('')));
    }
}
