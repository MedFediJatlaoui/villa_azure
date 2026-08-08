<?php

namespace IAWP\AJAX;

/** @internal */
class Clear_Favorite_Report extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_clear_favorite_report';
    }
    protected function action_callback() : void
    {
        \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_id');
        \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_type');
        \wp_send_json_success([]);
    }
}
