<?php

namespace IAWP\Rows;

use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Models\Geo;
use IAWP\Query_Taps;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Cities extends \IAWP\Rows\Rows
{
    public function attach_filters(Builder $query) : void
    {
        $query->joinSub($this->query(\true), 'city_rows', function (JoinClause $join) {
            $join->on('city_rows.city_id', '=', 'sessions.city_id');
        });
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Geo($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'city';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $clicks_subquery = Illuminate_Builder::new()->select('sessions.city_id')->selectRaw('COUNT(*) AS clicks')->from(Tables::clicks(), 'clicks')->join(Tables::views() . ' AS views', 'clicks.view_id', '=', 'views.id')->join(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config, ['clicks']))->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when($this->is_real_time(), function (Builder $query) {
            $query->whereNull('views.next_view_id');
        })->when(!$this->is_real_time(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->groupBy('sessions.city_id');
        $orders_subquery = Illuminate_Builder::new()->select('sessions.city_id')->selectRaw('COUNT(*) AS orders')->selectRaw('IFNULL(CAST(SUM(total) AS SIGNED), 0) AS gross_sales')->selectRaw('IFNULL(CAST(SUM(total_refunded) AS SIGNED), 0) AS total_refunded')->selectRaw('IFNULL(CAST(SUM(total_refunds) AS SIGNED), 0) AS total_refunds')->selectRaw('IFNULL(CAST(SUM(total - total_refunded) AS SIGNED), 0) AS net_sales')->from(Tables::orders(), 'orders')->join(Tables::views() . ' AS views', 'orders.initial_view_id', '=', 'views.id')->join(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->where('is_included_in_analytics', '=', \true)->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when($this->is_real_time(), function (Builder $query) {
            $query->whereNull('views.next_view_id');
        })->when(!$this->is_real_time(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->groupBy('sessions.city_id');
        $form_submissions_subquery = Illuminate_Builder::new()->select('sessions.city_id')->selectRaw('COUNT(*) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw('CAST(SUM(IF(form_id = ?, 1, 0)) AS SIGNED) AS ' . $form->submissions_column(), [$form->id()]);
            }
        })->from(Tables::form_submissions(), 'form_submissions')->join(Tables::views() . ' AS views', 'form_submissions.view_id', '=', 'views.id')->join(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when($this->is_real_time(), function (Builder $query) {
            $query->whereNull('views.next_view_id');
        })->when(!$this->is_real_time(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->groupBy('sessions.city_id');
        $session_duration_query = Illuminate_Builder::new()->select('sessions.city_id')->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at)) AS average_session_duration')->from(Tables::sessions(), 'sessions')->whereIn('sessions.session_id', function ($subquery) {
            $subquery->select('views.session_id')->from(Tables::views(), 'views')->join(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when($this->is_real_time(), function (Builder $query) {
                $query->whereNull('views.next_view_id');
            })->when(!$this->is_real_time(), function (Builder $query) {
                $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
            });
        })->groupBy('sessions.city_id');
        $cities_query = Illuminate_Builder::new()->select('countries.country_id', 'countries.country_code', 'countries.country', 'countries.continent', 'cities.subdivision', 'cities.city', 'cities.city_id')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS visitors')->selectRaw('COUNT(DISTINCT sessions.session_id) AS sessions')->selectRaw('MAX(session_durations.average_session_duration) AS average_session_duration')->selectRaw('COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL)) AS bounces')->from(Tables::sessions() . ' AS sessions')->leftJoin(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->leftJoin(Tables::cities() . ' AS cities', 'sessions.city_id', '=', 'cities.city_id')->leftJoin(Tables::countries() . ' AS countries', 'cities.country_id', '=', 'countries.country_id')->leftJoinSub($session_duration_query, 'session_durations', 'sessions.city_id', '=', 'session_durations.city_id')->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->whereNotNull('sessions.city_id')->groupBy('sessions.city_id')->having('views', '>', 0)->tap(fn(Builder $query) => $this->apply_record_filters($query))->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when($this->is_real_time(), function (Builder $query) {
            $query->whereNull('views.next_view_id');
        })->when(!$this->is_real_time(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->when(\is_int($this->solo_record_id), function (Builder $query) {
            $query->where('cities.city_id', '=', $this->solo_record_id);
        })->when($this->can_order_and_limit_at_record_level(), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        $previous_period_query = Illuminate_Builder::new()->select('sessions.city_id')->selectRaw('COUNT(DISTINCT views.id) AS previous_period_views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS previous_period_visitors')->from(Tables::views(), 'views')->leftJoin(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->tap(Query_Taps::tap_related_to_examined_record_for_previous_period($this->examiner_config, ['views', 'sessions']))->whereNotNull('sessions.city_id')->whereBetween('sessions.created_at', $this->get_previous_period_iso_range())->groupBy('sessions.city_id');
        $outer_query = Illuminate_Builder::new()->selectRaw('cities.*')->selectRaw('IF(sessions = 0, 0, views / sessions) AS views_per_session')->selectRaw('IFNULL((views - previous_period_views) / previous_period_views * 100, 0) AS views_growth')->selectRaw('IFNULL((visitors - previous_period_visitors) / previous_period_visitors * 100, 0) AS visitors_growth')->selectRaw('IFNULL(bounces / sessions * 100, 0) AS bounce_rate')->selectRaw('IFNULL(click_stats.clicks, 0) AS clicks')->selectRaw('IFNULL(order_stats.orders, 0) AS wc_orders')->selectRaw('IFNULL(order_stats.gross_sales, 0) AS wc_gross_sales')->selectRaw('IFNULL(order_stats.total_refunded, 0) AS wc_refunded_amount')->selectRaw('IFNULL(order_stats.total_refunds, 0) AS wc_refunds')->selectRaw('ROUND(CAST(IFNULL(order_stats.gross_sales, 0) - IFNULL(order_stats.total_refunded, 0) AS SIGNED)) AS wc_net_sales')->selectRaw('IF(visitors = 0, 0, (IFNULL(order_stats.orders, 0) / visitors) * 100) AS wc_conversion_rate')->selectRaw('IF(visitors = 0, 0, (IFNULL(order_stats.gross_sales, 0) - IFNULL(order_stats.total_refunded, 0)) / visitors) AS wc_earnings_per_visitor')->selectRaw('IF(IFNULL(order_stats.orders, 0) = 0, 0, ROUND(CAST(IFNULL(order_stats.gross_sales, 0) / order_stats.orders AS SIGNED))) AS wc_average_order_volume')->selectRaw('IFNULL(form_submission_stats.form_submissions, 0) AS form_submissions')->selectRaw('IF(visitors = 0, 0, (IFNULL(form_submission_stats.form_submissions, 0) / visitors) * 100) AS form_conversion_rate')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $column = $form->submissions_column();
                $query->selectRaw("IFNULL(form_submission_stats.{$column}, 0) AS {$column}");
                $query->selectRaw("IF(visitors = 0, 0, (IFNULL(form_submission_stats.{$column}, 0) / visitors) * 100) AS {$form->conversion_rate_column()}");
            }
        })->fromSub($cities_query, 'cities')->leftJoinSub($clicks_subquery, 'click_stats', 'click_stats.city_id', '=', 'cities.city_id')->leftJoinSub($orders_subquery, 'order_stats', 'order_stats.city_id', '=', 'cities.city_id')->leftJoinSub($form_submissions_subquery, 'form_submission_stats', 'form_submission_stats.city_id', '=', 'cities.city_id')->leftJoinSub($previous_period_query, 'previous_period_stats', 'previous_period_stats.city_id', '=', 'cities.city_id')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->when(!$this->can_order_and_limit_at_record_level() && !($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        }
        return $outer_query;
    }
}
