<?php

namespace IAWP;

use IAWP\Cron\Unscheduler;
use IAWP\Data_Pruning\Pruning_Scheduler;
/** @internal */
class ActivationLifecycle
{
    public static function handle_activation()
    {
        \wp_mkdir_p(\IAWPSCOPED\iawp_temp_path_to('template-cache'));
        if (\IAWPSCOPED\iawp_db_version() === 0) {
            // If there is no database installed, run migration on current process
            \IAWP\Migrations\Migrations::create_or_migrate();
        } else {
            // If there is a database, run migration in a background process
            \IAWP\Migrations\Migration_Job::maybe_dispatch();
        }
        (new \IAWP\Geo_Database_Manager())->health_check();
        \update_option('iawp_need_clear_cache', \true, \true);
        if (\get_option('iawp_show_gsg') == '') {
            \update_option('iawp_show_gsg', '1', \true);
        }
        \IAWPSCOPED\iawp()->cron_manager->schedule();
        (new Pruning_Scheduler())->schedule();
        if (\IAWPSCOPED\iawp_is_pro()) {
            \IAWPSCOPED\iawp()->email_reports->schedule();
        }
        // Set current version for changelog notifications
        \update_option('iawp_last_update_viewed', \IAWP_VERSION, \true);
        if (\IAWPSCOPED\iawp_db_version() > 0 && \IAWP\Database::is_missing_all_tables()) {
            \update_option('iawp_missing_tables', '1', \true);
        }
    }
    public static function handle_deactivation()
    {
        Unscheduler::unschedule_all_events();
        (new \IAWP\Geo_Database_Manager())->delete_database();
        \wp_delete_file(\trailingslashit(\WPMU_PLUGIN_DIR) . 'iawp-performance-boost.php');
        \delete_option('iawp_must_use_directory_not_writable');
    }
}
