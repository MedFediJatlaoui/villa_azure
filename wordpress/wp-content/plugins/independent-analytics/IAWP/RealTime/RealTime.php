<?php

namespace IAWP\RealTime;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Click_Tracking\Click_Processing_Job;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Examiner_Config;
use IAWP\Icon_Directory_Factory;
use IAWP\Map_Data;
use IAWP\Rows\Countries;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Table_Campaigns;
use IAWP\Tables\Table_Devices;
use IAWP\Tables\Table_Geo;
use IAWP\Tables\Table_Pages;
use IAWP\Tables\Table_Referrers;
use IAWP\Utils\Singleton;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class RealTime
{
    use Singleton;
    private ?CarbonImmutable $starting_at;
    private ?Examiner_Config $examiner_config;
    public function __construct(?CarbonImmutable $starting_at = null, ?Examiner_Config $examiner_config = null)
    {
        $this->starting_at = $starting_at ?? CarbonImmutable::now(Timezone::utc_timezone());
        $this->starting_at = $this->starting_at->ceilSeconds(10);
        $this->examiner_config = $examiner_config;
    }
    public function get_real_time_analytics() : array
    {
        // Clicks need to be processed before showing real-time data
        (new Click_Processing_Job())->handle();
        $five_minutes_ago = $this->starting_at->subMinutes(5)->subSeconds(10);
        $range = new Exact_Date_Range($five_minutes_ago->toDateTime(), $this->starting_at->toDateTime(), \false);
        $recent_traffic = new \IAWP\RealTime\RecentTraffic($range, $this->examiner_config);
        $visitors = $recent_traffic->visitor_count();
        $chart_data = ['short_labels' => $recent_traffic->short_labels(), 'long_labels' => $recent_traffic->long_labels(), 'timestamps' => $recent_traffic->timestamps(), 'views' => $recent_traffic->views(), 'orders' => $recent_traffic->orders(), 'clicks' => $recent_traffic->clicks(), 'form_submissions' => $recent_traffic->form_submissions()];
        $geo_table = new Table_Geo();
        $countries = new Countries($range, $geo_table->sanitize_sort_parameters('visitors'));
        if ($this->examiner_config) {
            if ($geo_table->id() === $this->examiner_config->type()) {
                $countries->limit_to($this->examiner_config->id());
            } elseif ($geo_table->id() !== $this->examiner_config->type()) {
                $countries->for_examiner($this->examiner_config);
            }
        }
        $map_data = new Map_Data($countries->rows());
        $minimum_items_per_list = 10;
        $lists = [];
        $tables = [new Table_Pages($this->get_group_preference('views')), new Table_Referrers($this->get_group_preference('referrers')), new Table_Devices($this->get_group_preference('devices')), new Table_Campaigns($this->get_group_preference('campaigns', 'utm_campaign')), new Table_Geo($this->get_group_preference('geo'))];
        foreach ($tables as $table) {
            $group = $table->group();
            $rows_class = $group->rows_class();
            $rows = new $rows_class($range, $table->sanitize_sort_parameters('visitors'));
            $list_items = [];
            if ($this->examiner_config) {
                if ($table->id() === $this->examiner_config->type()) {
                    $rows->limit_to($this->examiner_config->id());
                } elseif ($table->id() !== $this->examiner_config->type()) {
                    $rows->for_examiner($this->examiner_config);
                }
            }
            foreach ($rows->rows() as $index => $row) {
                $title_column = $group->title_column();
                $icon = null;
                if ($table->id() === 'geo') {
                    // Can this be on a row insteade
                    $icon = Icon_Directory_Factory::flags()->find($row->country_code());
                }
                $list_items[] = ['id' => $row->id(), 'position' => $index + 1, 'group' => $group->singular(), 'title' => $row->{$title_column}(), 'visitors' => $row->visitors(), 'icon' => $icon];
            }
            $groups = Collection::make($table->groups()->groups())->filter(function (Group $group) {
                if ($group->id() === 'campaign') {
                    return \false;
                }
                return \true;
            })->map(function (Group $group) {
                return [$group->id(), $group->singular()];
            })->all();
            $lists[$table->id()] = ['id' => $table->id(), 'title' => $table->name(), 'group_id' => $group->id(), 'group_title' => $group->singular(), 'groups' => $groups, 'count' => \number_format_i18n(\count($list_items)), 'column' => \__('Visitors', 'independent-analytics'), 'entries' => $this->fill_rows_with_blanks($list_items, $minimum_items_per_list), 'icon' => \IAWPSCOPED\iawp_render('icons.' . $table->id()), 'empty' => \__('No data found', 'independent-analytics')];
        }
        return ['visitors' => $visitors, 'starting_at' => $this->starting_at->format('Uv'), 'country_data' => $map_data->get_country_data(), 'chart_data' => $chart_data, 'lists' => $lists];
    }
    public function get_group_preference(string $table_id, ?string $default = null) : ?string
    {
        $groups = \get_user_meta(\get_current_user_id(), 'iawp_real_time_groups', \true);
        if (!\is_array($groups) || !\array_key_exists($table_id, $groups)) {
            return $default;
        }
        return $groups[$table_id];
    }
    public function render_real_time_analytics()
    {
        echo \IAWPSCOPED\iawp_render('real-time', $this->get_real_time_analytics());
    }
    private function fill_rows_with_blanks(array $rows, $items_per_list) : array
    {
        $number_of_rows = \count($rows);
        $blanks_to_add = $items_per_list - $number_of_rows;
        // Nothing to add
        if ($blanks_to_add < 1) {
            return $rows;
        }
        for ($i = 0; $i < $blanks_to_add; $i++) {
            $position = $number_of_rows + $i + 1;
            $rows[] = ['id' => 'blank-' . $position, 'title' => '', 'position' => $position, 'visitors' => '', 'blank' => \true];
        }
        return $rows;
    }
}
