<?php
/**
 * Villa Azur — correct the canonical URL on translated front pages.
 *
 * Rank Math special-cases the front page and derives its canonical from
 * home_url(), which always returns the DEFAULT language's home. Polylang serves
 * /en/, /de/, /pl/, /ru/ as front pages by rewriting the main query to the
 * translated page (see PLL_Frontend_Static_Pages::page_on_front_query()), so
 * Rank Math still saw "front page" and emitted the French URL — meaning every
 * translated homepage declared itself a duplicate of the French one. Google
 * honours rel=canonical over hreflang for indexing, so those four homepages
 * would have been collapsed into the French page and never ranked in their own
 * markets, defeating the whole point of the per-language URLs.
 *
 * Translated INNER pages were already correct (Rank Math uses the queried
 * object's permalink there), so this is scoped narrowly to the front page.
 */

add_filter('rank_math/frontend/canonical', function ($canonical) {
    if (!is_front_page() || !function_exists('pll_current_language') || !function_exists('pll_home_url')) {
        return $canonical;
    }

    $lang = pll_current_language();
    if (empty($lang)) {
        return $canonical;
    }

    $home = pll_home_url($lang);
    return $home ? $home : $canonical;
});
