<?php
/**
 * Villa Azur — stop Polylang from managing ElementsKit's header/footer templates.
 *
 * Polylang, once active, filters ANY "translated" post type's queries by the
 * current language via its `pre_get_posts` hook. ElementsKit's Header/Footer
 * module queries its own `elementskit_template` custom post type to find the
 * active header/footer (see modules/header-footer/activator.php), and those
 * template posts never get a language assigned (they're meant to be shared
 * across every language, not per-language content). Once Polylang started
 * managing that post type, its language filter silently excluded them from
 * every query — `Activator::the_filter()` found nothing, so no header/footer
 * rendered on ANY page, in ANY language (confirmed via debug logging: the
 * exact same query returns the templates via WP-CLI, where Polylang hasn't
 * resolved a "current language" yet, but returns empty on a real front-end
 * request where it has).
 *
 * Per Polylang's own filter doc: must be added "in a function hooked to
 * plugins_loaded or directly in functions.php" — an mu-plugin's top-level
 * code runs well before that, so this is registered unconditionally here
 * (harmless no-op if Polylang isn't active).
 *
 * Priority 999 is deliberate: both elementskit-lite and royal-elementor-addons
 * ship a wpml-config.xml declaring their template CPTs (`elementskit_template`,
 * `wpr_templates`) as translatable, which Polylang's own WPML-compat module
 * reads and re-adds via this SAME filter at priority 10. Registering at the
 * default priority just lost that race (same priority, but Polylang's
 * WPML-config callback happens to register after this file loads) — a much
 * later priority guarantees this callback always runs last, so the unset()
 * actually sticks.
 */

add_filter('pll_get_post_types', function ($post_types, $is_settings) {
    unset($post_types['elementskit_template']);
    unset($post_types['wpr_templates']);
    unset($post_types['elementor_library']); // same reasoning: shared templates, not per-language content.
    return $post_types;
}, 999, 2);
