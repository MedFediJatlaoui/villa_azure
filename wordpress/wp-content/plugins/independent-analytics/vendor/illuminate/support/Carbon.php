<?php

namespace IAWPSCOPED\Illuminate\Support;

use IAWPSCOPED\Carbon\Carbon as BaseCarbon;
use IAWPSCOPED\Carbon\CarbonImmutable as BaseCarbonImmutable;
use IAWPSCOPED\Illuminate\Support\Traits\Conditionable;
/** @internal */
class Carbon extends BaseCarbon
{
    use Conditionable;
    /**
     * {@inheritdoc}
     */
    public static function setTestNow($testNow = null)
    {
        BaseCarbon::setTestNow($testNow);
        BaseCarbonImmutable::setTestNow($testNow);
    }
}
