<?php

namespace IAWPSCOPED\Illuminate\Contracts\Database\Eloquent;

/** @internal */
interface Castable
{
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return class-string<CastsAttributes|CastsInboundAttributes>|CastsAttributes|CastsInboundAttributes
     */
    public static function castUsing(array $arguments);
}
