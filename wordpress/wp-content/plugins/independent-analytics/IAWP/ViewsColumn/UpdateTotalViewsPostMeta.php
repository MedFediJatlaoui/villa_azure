<?php

namespace IAWP\ViewsColumn;

use IAWP\Illuminate_Builder;
use IAWP\Models\Page;
use IAWP\Models\Page_Home;
use IAWP\Models\Page_Post_Type_Archive;
use IAWP\Models\Page_Singular;
use IAWP\Query;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class UpdateTotalViewsPostMeta
{
    private function update_singular(int $singular_id) : void
    {
        $views_table = Query::get_table_name(Query::VIEWS);
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('resources.id', '=', 'views.resource_id');
        })->where('singular_id', '=', $singular_id)->value('views');
        \update_post_meta($singular_id, \IAWP\ViewsColumn\ViewsColumn::$meta_key, $total_views);
    }
    private function update_home() : void
    {
        $id = $this->get_home_id();
        if ($id === null) {
            return;
        }
        $views_table = Query::get_table_name(Query::VIEWS);
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('resources.id', '=', 'views.resource_id');
        })->where('resource', '=', 'home')->value('views');
        \update_post_meta($id, \IAWP\ViewsColumn\ViewsColumn::$meta_key, $total_views);
    }
    private function get_home_id() : ?int
    {
        $id = \get_option('page_for_posts');
        if (\is_string($id) && \ctype_digit($id)) {
            $id = \intval($id);
        }
        $blog_page = \get_post($id);
        if ($blog_page === null) {
            return null;
        }
        return $id;
    }
    private function update_shop() : void
    {
        try {
            $id = $this->get_shop_id();
            if ($id === null) {
                return;
            }
            $views_table = Query::get_table_name(Query::VIEWS);
            $resources_table = Query::get_table_name(Query::RESOURCES);
            $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
                $join->on('resources.id', '=', 'views.resource_id');
            })->where('resource', '=', 'post_type_archive')->where('post_type', '=', 'product')->value('views');
            \update_post_meta($id, \IAWP\ViewsColumn\ViewsColumn::$meta_key, $total_views);
        } catch (\Throwable $e) {
        }
    }
    private function get_shop_id() : ?int
    {
        if (!\function_exists('IAWPSCOPED\\wc_get_page_id')) {
            return null;
        }
        $id = wc_get_page_id('shop');
        if ($id === -1) {
            return null;
        }
        return $id;
    }
    public static function update_all() : void
    {
        $updater = new \IAWP\ViewsColumn\UpdateTotalViewsPostMeta();
        $updater->update_home();
        $updater->update_shop();
        $ids = \get_posts(['post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
        foreach ($ids as $id) {
            $id = (int) $id;
            // Skip these as they're handled separately
            if ($updater->get_home_id() === $id || $updater->get_shop_id() === $id) {
                continue;
            }
            $updater->update_singular($id);
        }
    }
    public static function for_page(Page $page) : void
    {
        $updater = new \IAWP\ViewsColumn\UpdateTotalViewsPostMeta();
        if ($page instanceof Page_Singular) {
            $singular_id = $page->get_singular_id();
            if (\is_int($singular_id)) {
                $updater->update_singular($singular_id);
            }
        } elseif ($page instanceof Page_Home) {
            $updater->update_home();
        } elseif ($page instanceof Page_Post_Type_Archive && $page->post_type() === 'product') {
            $updater->update_shop();
        }
    }
}
