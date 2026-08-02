<?php

namespace IAWP;

use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Utils\Number_Formatter;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
/** @internal */
class Admin_Bar_Stats
{
    private ?\IAWP\Resource_Identifier $resource_identifier;
    public function __construct()
    {
        $this->resource_identifier = \IAWP\Resource_Identifier::for_resource_being_edited() ?? \IAWP\Resource_Identifier::for_resource_being_viewed();
    }
    public function should_render_ui() : bool
    {
        if (self::is_feature_enabled() && $this->resource_identifier) {
            return \true;
        }
        return \false;
    }
    public function get_menu_bar_items() : array
    {
        $today = new Relative_Date_Range('TODAY');
        $yesterday = new Relative_Date_Range('YESTERDAY');
        $last_thirty = new Relative_Date_Range('LAST_THIRTY');
        $all_time = new Relative_Date_Range('ALL_TIME');
        $views_today = $this->get_views_in_date_range($today);
        $views_yesterday = $this->get_views_in_date_range($yesterday);
        $views_last_thirty = $this->get_views_in_date_range($last_thirty);
        $views_total = $this->get_views_in_date_range($all_time);
        $views_today = Number_Formatter::decimal($views_today);
        $views_yesterday = Number_Formatter::decimal($views_yesterday);
        $views_last_thirty = Number_Formatter::decimal($views_last_thirty);
        $views_total = Number_Formatter::decimal($views_total);
        return [['id' => 'iawp_admin_bar', 'title' => '<span class="ab-icon dashicons-analytics"></span>' . \sprintf('%s %s', $views_today, \esc_html__('Views', 'independent-analytics')), 'meta' => ['class' => 'iawp_admin_bar_button']], ['id' => 'iawp_admin_bar_group_title', 'title' => '<span>' . \esc_html__('Date', 'independent-analytics') . '</span> ' . '<span>' . \esc_html__('Views', 'independent-analytics') . '</span>', 'parent' => 'iawp_admin_bar'], ['id' => 'iawp_admin_bar_today', 'title' => '<span>' . \esc_html__('Today:', 'independent-analytics') . '</span> <span>' . \esc_html__($views_today) . '</span>', 'parent' => 'iawp_admin_bar'], ['id' => 'iawp_admin_bar_yesterday', 'title' => '<span>' . \esc_html__('Yesterday:', 'independent-analytics') . '</span> <span>' . \esc_html__($views_yesterday) . '</span>', 'parent' => 'iawp_admin_bar'], ['id' => 'iawp_admin_bar_last_thirty', 'title' => '<span>' . \esc_html__('Last 30 Days:', 'independent-analytics') . '</span> <span>' . \esc_html__($views_last_thirty) . '</span>', 'parent' => 'iawp_admin_bar'], ['id' => 'iawp_admin_bar_total', 'title' => '<span>' . \esc_html__('All Time:', 'independent-analytics') . '</span> <span>' . \esc_html__($views_total) . '</span>', 'parent' => 'iawp_admin_bar'], ['id' => 'iawp_admin_bar_dashboard_group', 'parent' => 'iawp_admin_bar', 'is_group' => \true], ['id' => 'iawp_admin_bar_dashboard_link', 'title' => \esc_html__('Analytics Dashboard', 'independent-analytics') . ' &rarr;', 'href' => \esc_url(\IAWPSCOPED\iawp_dashboard_url()), 'parent' => 'iawp_admin_bar_dashboard_group']];
    }
    public function get_views_in_date_range(Date_Range $date_range) : int
    {
        $resource = $this->resource_identifier;
        $view_query = \IAWP\Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from(\IAWP\Tables::resources() . ' AS resources')->join(\IAWP\Tables::views() . ' AS views', 'views.resource_id', '=', 'resources.id')->where('resource', '=', $resource->type())->when($resource->has_meta(), function (Builder $query) use($resource) {
            $query->where($resource->meta_key(), '=', $resource->meta_value());
        })->whereBetween('views.viewed_at', [$date_range->iso_start(), $date_range->iso_end()]);
        $views = $view_query->value('views');
        if (\is_string($views)) {
            if (\is_numeric($views)) {
                $views = \intval($views);
            } else {
                $views = null;
            }
        }
        return $views ?? 0;
    }
    public static function is_feature_enabled() : bool
    {
        return \IAWPSCOPED\iawp()->get_option('iawp_disable_admin_toolbar_analytics', \false) === \false && \IAWP\Capability_Manager::can_view();
    }
    public static function register() : void
    {
        \add_action('admin_bar_menu', function ($admin_bar) {
            $admin_bar_stats = new self();
            if (!$admin_bar_stats->should_render_ui()) {
                return;
            }
            foreach ($admin_bar_stats->get_menu_bar_items() as $menu_bar_item) {
                $is_group = $menu_bar_item['is_group'] ?? \false;
                // Should the item be registered as a group or a node?
                if ($is_group) {
                    $admin_bar->add_group($menu_bar_item);
                } else {
                    $admin_bar->add_node($menu_bar_item);
                }
            }
        }, 100);
    }
}
