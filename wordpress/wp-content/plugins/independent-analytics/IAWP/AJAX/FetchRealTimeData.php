<?php

namespace IAWP\AJAX;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Examiner_Config;
use IAWP\RealTime\RealTime;
use IAWP\Utils\Timezone;
/** @internal */
class FetchRealTimeData extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_fetch_real_time_data';
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    protected function action_callback() : void
    {
        $examiner_config = Examiner_Config::make(['type' => $this->get_field('listId'), 'group' => $this->get_field('groupId'), 'id' => $this->get_int_field('rowId')]);
        $starting_at = null;
        $timestamp = $this->get_int_field('startingAt');
        if (\is_int($timestamp)) {
            $starting_at = CarbonImmutable::createFromTimestampMs($timestamp, Timezone::utc_timezone());
        }
        $real_time = new RealTime($starting_at, $examiner_config);
        \wp_send_json_success($real_time->get_real_time_analytics());
    }
}
