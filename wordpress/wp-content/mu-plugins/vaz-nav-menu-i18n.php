<?php
/**
 * Villa Azur — serve the current language's nav menu.
 *
 * Polylang's own menu translation works by assigning a different menu per
 * language to a THEME LOCATION. This site's header doesn't use a theme
 * location: it's an ElementsKit template whose `ekit-nav-menu` widget hardcodes
 * a menu ID in its Elementor settings (`elementskit_nav_menu` => "5"), so
 * Polylang never gets a say and every language rendered the French menu —
 * meaning French labels AND French URLs, which is what dropped the /en/ prefix
 * as soon as a guest clicked any menu item.
 *
 * ElementsKit renders through core `wp_nav_menu()` (widgets/nav-menu/nav-menu.php),
 * so `wp_nav_menu_args` is a supported, upgrade-safe hook point: we swap the
 * requested menu for its per-language sibling.
 *
 * Convention: for a menu with slug `foo`, the translation for language `xx` is
 * the menu with slug `foo-xx` (created by scratchpad/build-lang-menus.php).
 * Those menus' items point at the translated pages and carry EMPTY titles, so
 * their labels come from the translated page titles automatically — nothing to
 * re-translate here when a page title changes. Missing sibling => untouched
 * (default language, or a menu we haven't translated), so this is always safe.
 */

add_filter('wp_nav_menu_args', function ($args) {
    if (!function_exists('pll_current_language') || !function_exists('pll_default_language')) {
        return $args;
    }

    $lang = pll_current_language();
    if (empty($lang) || $lang === pll_default_language()) {
        return $args; // default language uses the original menu as-is
    }

    if (empty($args['menu'])) {
        return $args;
    }

    // $args['menu'] may be an ID, a slug, a name, or a term object.
    $menu = wp_get_nav_menu_object($args['menu']);
    if (!$menu) {
        return $args;
    }

    $translated = wp_get_nav_menu_object($menu->slug . '-' . $lang);
    if ($translated) {
        $args['menu'] = $translated->term_id;
    }

    return $args;
});
