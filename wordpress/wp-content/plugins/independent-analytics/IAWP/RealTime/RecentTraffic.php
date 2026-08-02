<?php

namespace IAWP\RealTime;

use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Examiner_Config;
use IAWP\Illuminate_Builder;
use IAWP\Query_Taps;
use IAWP\Tables;
/** @internal */
class RecentTraffic
{
    private array $short_labels;
    private array $long_labels;
    private array $timestamps;
    private int $visitors;
    private array $views;
    private array $orders;
    private array $clicks;
    private array $form_submissions;
    public function __construct(Exact_Date_Range $range, ?Examiner_Config $examiner_config)
    {
        $this->populate_data($range, $examiner_config);
    }
    public function short_labels() : array
    {
        return $this->short_labels;
    }
    public function long_labels() : array
    {
        return $this->long_labels;
    }
    public function timestamps() : array
    {
        return $this->timestamps;
    }
    public function visitor_count() : int
    {
        return $this->visitors;
    }
    public function views() : array
    {
        return $this->views;
    }
    public function view_count() : int
    {
        return \array_sum($this->views);
    }
    public function orders() : array
    {
        return $this->orders;
    }
    public function order_count() : int
    {
        return \array_sum($this->orders);
    }
    public function clicks() : array
    {
        return $this->clicks;
    }
    public function click_count() : int
    {
        return \array_sum($this->clicks);
    }
    public function form_submissions() : array
    {
        return $this->form_submissions;
    }
    public function form_submission_count() : int
    {
        return \array_sum($this->form_submissions);
    }
    private function populate_data(Exact_Date_Range $range, ?Examiner_Config $examiner_config) : void
    {
        $visitors_query = Illuminate_Builder::new()->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS visitors')->from(Tables::views(), 'views')->leftJoin(Tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id')->tap(Query_Taps::tap_related_to_examined_record($examiner_config))->whereBetween('viewed_at', [$range->iso_start(), $range->iso_end()]);
        $visitors = $visitors_query->value('visitors');
        $clicks_query = Illuminate_Builder::new()->selectRaw('0 AS views')->selectRaw('COUNT(*) AS clicks')->selectRaw('0 AS form_submissions')->selectRaw('0 AS orders')->selectRaw("ABS(CEILING(TIMESTAMPDIFF(SECOND, '{$range->iso_end()}', clicks.created_at) / 10)) AS interval_ago")->from(Tables::clicks(), 'clicks')->leftJoin(Tables::views() . ' AS views', 'views.id', '=', 'clicks.view_id')->leftJoin(Tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id')->tap(Query_Taps::tap_related_to_examined_record($examiner_config))->whereBetween('clicks.created_at', [$range->iso_start(), $range->iso_end()])->groupBy('interval_ago');
        $orders_query = Illuminate_Builder::new()->selectRaw('0 AS views')->selectRaw('0 AS clicks')->selectRaw('0 AS form_submissions')->selectRaw('COUNT(*) AS orders')->selectRaw("ABS(CEILING(TIMESTAMPDIFF(SECOND, '{$range->iso_end()}', orders.created_at) / 10)) AS interval_ago")->from(Tables::orders(), 'orders')->leftJoin(Tables::views() . ' AS views', 'views.id', '=', 'orders.view_id')->leftJoin(Tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id')->tap(Query_Taps::tap_related_to_examined_record($examiner_config))->whereBetween('orders.created_at', [$range->iso_start(), $range->iso_end()])->groupBy('interval_ago');
        $form_submissions_query = Illuminate_Builder::new()->selectRaw('0 AS views')->selectRaw('0 AS clicks')->selectRaw('COUNT(*) AS form_submissions')->selectRaw('0 AS orders')->selectRaw("ABS(CEILING(TIMESTAMPDIFF(SECOND, '{$range->iso_end()}', form_submissions.created_at) / 10)) AS interval_ago")->from(Tables::form_submissions(), 'form_submissions')->leftJoin(Tables::views() . ' AS views', 'views.id', '=', 'form_submissions.view_id')->leftJoin(Tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id')->tap(Query_Taps::tap_related_to_examined_record($examiner_config))->whereBetween('form_submissions.created_at', [$range->iso_start(), $range->iso_end()])->groupBy('interval_ago');
        $views_query = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->selectRaw('0 AS clicks')->selectRaw('0 AS form_submissions')->selectRaw('0 AS orders')->selectRaw("ABS(CEILING(TIMESTAMPDIFF(SECOND, '{$range->iso_end()}', views.viewed_at) / 10)) AS interval_ago")->from(Tables::views(), 'views')->leftJoin(Tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id')->tap(Query_Taps::tap_related_to_examined_record($examiner_config))->whereBetween('viewed_at', [$range->iso_start(), $range->iso_end()])->unionAll($clicks_query)->unionAll($form_submissions_query)->unionAll($orders_query)->groupBy('interval_ago');
        $union_query = Illuminate_Builder::new()->select(['interval_ago'])->selectRaw('SUM(views) AS views')->selectRaw('SUM(clicks) AS clicks')->selectRaw('SUM(form_submissions) AS form_submissions')->selectRaw('SUM(orders) AS orders')->from($views_query)->groupBy('interval_ago')->orderBy('interval_ago');
        // Illuminate_Builder::ray($union_query);
        $rows = $union_query->get()->all();
        $interval = new \DateInterval('PT10S');
        $period = new \DatePeriod($range->start(), $interval, $range->end());
        foreach ($period as $interval => $date) {
            $views = 0;
            $orders = 0;
            $clicks = 0;
            $form_submissions = 0;
            // Search the rows for a matching interval to pull data from
            foreach ($rows as $row) {
                if (\intval($row->interval_ago) !== $interval) {
                    continue;
                }
                $views = \intval($row->views);
                $orders = \intval($row->orders);
                $clicks = \intval($row->clicks);
                $form_submissions = \intval($row->form_submissions);
                break;
            }
            $this->short_labels[] = $this->get_short_label($interval);
            $this->long_labels[] = $this->get_long_label($interval);
            $this->timestamps[] = $date->getTimestamp();
            $this->views[] = $views;
            $this->orders[] = $orders;
            $this->clicks[] = $clicks;
            $this->form_submissions[] = $form_submissions;
        }
        $this->short_labels = \array_reverse($this->short_labels);
        $this->long_labels = \array_reverse($this->long_labels);
        $this->timestamps = \array_reverse($this->timestamps);
        $this->visitors = $visitors;
        $this->views = \array_reverse($this->views);
        $this->orders = \array_reverse($this->orders);
        $this->clicks = \array_reverse($this->clicks);
        $this->form_submissions = \array_reverse($this->form_submissions);
    }
    private function get_short_label(int $interval) : string
    {
        if ($interval === 0) {
            return \__('Now', 'independent-analytics');
        }
        $seconds_passed = $interval * 10;
        $is_whole_minute = $seconds_passed % 60 === 0;
        if (!$is_whole_minute) {
            // Don't show short labels for partial minutes
            return '';
        }
        $minutes_passed = $seconds_passed / 60;
        if ($minutes_passed === 1) {
            return '-' . $minutes_passed . ' ' . \__('min', 'independent-analytics');
        }
        return '-' . $minutes_passed . ' ' . \__('mins', 'independent-analytics');
    }
    private function get_long_label(int $interval) : string
    {
        if ($interval === 0) {
            return \__('Now', 'independent-analytics');
        }
        $seconds_passed = $interval * 10;
        return $seconds_passed . ' ' . \__('seconds ago', 'independent-analytics');
    }
}
