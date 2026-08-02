<?php

namespace IAWPSCOPED\Illuminate\Database\Console\Seeds;

use IAWPSCOPED\Illuminate\Database\Eloquent\Model;
/** @internal */
trait WithoutModelEvents
{
    /**
     * Prevent model events from being dispatched by the given callback.
     *
     * @param  callable  $callback
     * @return callable
     */
    public function withoutModelEvents(callable $callback)
    {
        return fn() => Model::withoutEvents($callback);
    }
}
