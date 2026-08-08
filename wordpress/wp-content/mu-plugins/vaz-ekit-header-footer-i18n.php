<?php
/**
 * Villa Azur — serve the current language's ElementsKit header/footer template.
 *
 * Background: vaz-polylang-cpt-exclude.php had to take `elementskit_template`
 * out of Polylang's hands so the header/footer would render at all (Polylang's
 * language filter was hiding those posts from ElementsKit's own query, killing
 * the header sitewide). The side effect was a single shared French header and
 * footer for all five languages. This file restores per-language selection,
 * but under our explicit control rather than Polylang's query filtering.
 *
 * How: ElementsKit's `Activator::template_ids()` opens with
 *   $cached = wp_cache_get( 'elementskit_template_ids' );
 *   if ( false !== $cached ) { return $cached; }
 * so pre-seeding that key short-circuits its lookup entirely. `Activator::hooks()`
 * runs on `wp` at priority 10, so seeding at priority 5 lands first, and the
 * view templates (theme-support-header.php / -footer.php) call the same cached
 * accessor later in the request — one decision, applied consistently.
 *
 * The French templates stay the only ones flagged
 * `elementskit_template_activation = yes`, so if this file is ever removed the
 * site falls back to today's behaviour (French header/footer everywhere) rather
 * than to no header at all.
 *
 * Because we bypass template_ids(), we must replicate the one side effect it has
 * besides returning ids: enqueueing each template's generated Elementor CSS.
 */

add_action('wp', function () {
    if (is_admin() || !function_exists('pll_current_language') || !function_exists('pll_default_language')) {
        return;
    }

    $lang = pll_current_language();
    if (empty($lang) || $lang === pll_default_language()) {
        return; // default language: let ElementsKit resolve the active pair itself
    }

    $map = get_option('vaz_ekit_template_map', []);
    $header = $map[$lang]['header'] ?? 0;
    $footer = $map[$lang]['footer'] ?? 0;
    if (!$header && !$footer) {
        return;
    }

    // Fall back per-slot rather than all-or-nothing, so a missing translation of
    // one template never blanks out the other.
    if (!$header || 'publish' !== get_post_status($header)) {
        $header = null;
    }
    if (!$footer || 'publish' !== get_post_status($footer)) {
        $footer = null;
    }
    if (null === $header && null === $footer) {
        return;
    }

    wp_cache_set('elementskit_template_ids', array($header, $footer));

    if (class_exists('\ElementsKit_Lite\Utils')) {
        foreach (array($header, $footer) as $id) {
            if ($id) {
                \ElementsKit_Lite\Utils::render_elementor_content_css($id);
            }
        }
    }
}, 5);
