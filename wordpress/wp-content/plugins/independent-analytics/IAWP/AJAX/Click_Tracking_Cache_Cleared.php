<?php

namespace IAWP\AJAX;

/** @internal */
class Click_Tracking_Cache_Cleared extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_click_tracking_cache_cleared';
    }
    protected function requires_write_access() : bool
    {
        return \true;
    }
    protected function action_callback() : void
    {
        \update_option('iawp_click_tracking_cache_cleared', \true, \true);
    }
}
