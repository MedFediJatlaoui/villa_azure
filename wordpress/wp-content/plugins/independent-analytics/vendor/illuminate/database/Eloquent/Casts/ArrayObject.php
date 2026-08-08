<?php

namespace IAWPSCOPED\Illuminate\Database\Eloquent\Casts;

use ArrayObject as BaseArrayObject;
use IAWPSCOPED\Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
/**
 * @template TKey of array-key
 * @template TItem
 *
 * @extends  \ArrayObject<TKey, TItem>
 * @internal
 */
class ArrayObject extends BaseArrayObject implements Arrayable, JsonSerializable
{
    /**
     * Get a collection containing the underlying array.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collect()
    {
        return \IAWPSCOPED\collect($this->getArrayCopy());
    }
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getArrayCopy();
    }
    /**
     * Get the array that should be JSON serialized.
     *
     * @return array
     */
    public function jsonSerialize() : array
    {
        return $this->getArrayCopy();
    }
}
