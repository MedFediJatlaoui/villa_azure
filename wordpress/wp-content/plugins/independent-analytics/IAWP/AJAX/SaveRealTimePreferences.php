<?php

namespace IAWP\AJAX;

use IAWP\Env;
/** @internal */
class SaveRealTimePreferences extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_save_real_time_preferences';
    }
    protected function action_callback() : void
    {
        $table = $this->get_field('table');
        $group = $this->get_field('group');
        // Validate the table and group area real combination
        $table_class = Env::get_table($table);
        $table_instance = new $table_class($group);
        $groups = \get_user_meta(\get_current_user_id(), 'iawp_real_time_groups', \true);
        if (!\is_array($groups)) {
            $groups = [];
        }
        $groups[$table_instance->id()] = $table_instance->group()->id();
        \update_user_meta(\get_current_user_id(), 'iawp_real_time_groups', $groups);
    }
}
