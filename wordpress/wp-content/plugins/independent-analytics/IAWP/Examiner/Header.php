<?php

namespace IAWP\Examiner;

use IAWP\Tables;
/** @internal */
class Header
{
    public static function html(Tables\Table $table, $model) : string
    {
        return \IAWPSCOPED\iawp_render('examiner.header', ['type' => $table->id(), 'model' => $model, 'group' => $table->group()]);
    }
}
