<?php
/**
 * Villa Azur — language switcher.
 *
 * Header is built with ElementsKit (ekit-template-content-header), whose
 * Elementor JSON we don't want to hand-edit blind and risk corrupting — so
 * the switcher renders as a small fixed pill via wp_footer instead. It shows
 * on every page/popup regardless of theme/builder markup, and can be dragged
 * into the actual nav later (drop a Shortcode widget with [vaz_lang_switcher]
 * into the ElementsKit header template) without removing this fallback.
 *
 * Note: this is an mu-plugin, so it loads BEFORE regular plugins (including
 * Polylang) — function_exists('pll_the_languages') must only be checked at
 * call time (inside functions/hook callbacks), never at file-parse time,
 * or an early return here would silently skip registering everything below.
 */

function vaz_lang_switcher_html() {
    if (!function_exists('pll_the_languages')) {
        return '';
    }

    $languages = pll_the_languages([
        'raw'                    => 1,
        'hide_if_empty'          => 0,
        'hide_if_no_translation' => 0,
        'display_names_as'       => 'name',
        'show_flags'             => 1,
    ]);

    if (empty($languages) || !is_array($languages)) {
        return '';
    }

    $items = '';
    $currentFlag = '';
    foreach ($languages as $lang) {
        $isCurrent = !empty($lang['current_lang']);
        $classes = 'vaz-lang-item' . ($isCurrent ? ' is-current' : '');
        if ($isCurrent) {
            $currentFlag = $lang['flag'] ?? '';
        }
        $items .= sprintf(
            '<a href="%s" class="%s" hreflang="%s" lang="%s"><span class="vaz-lang-flag">%s</span><span class="vaz-lang-name">%s</span><span class="vaz-lang-code">%s</span></a>',
            esc_url($lang['url']),
            esc_attr($classes),
            esc_attr($lang['locale']),
            esc_attr($lang['locale']),
            $lang['flag'] ?? '',
            esc_html($lang['name']),
            esc_html(strtoupper($lang['slug']))
        );
    }

    return '<div class="vaz-lang-switcher"><button type="button" class="vaz-lang-toggle" aria-expanded="false" aria-label="Choisir la langue / Choose language">'
        . '<span class="vaz-lang-flag">' . $currentFlag . '</span>'
        . '<span class="vaz-lang-current">' . esc_html(strtoupper(pll_current_language())) . '</span>'
        . '</button><div class="vaz-lang-menu">' . $items . '</div></div>';
}
add_shortcode('vaz_lang_switcher', 'vaz_lang_switcher_html');

add_action('wp_footer', function () {
    echo vaz_lang_switcher_html();
});

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('pll_the_languages')) {
        return;
    }

    wp_register_style('vaz-lang-switcher', false);
    wp_enqueue_style('vaz-lang-switcher');
    // !important throughout: a bare <button> here otherwise inherits the
    // theme/ElementsKit global button reset (solid gold fill, 4px corners,
    // auto width) at equal-or-later cascade position, which is what made the
    // toggle render as a plain orange rectangle regardless of the rules below.
    // Background is white/frosted rather than solid gold on purpose: the
    // flags themselves are already colourful, so a bright gold toggle behind
    // them read as a clashing wall of yellow rather than a clean control.
    wp_add_inline_style('vaz-lang-switcher', '
        .vaz-lang-switcher{position:fixed;bottom:20px;right:20px;z-index:9999;font-family:"Plus Jakarta Sans",Arial,sans-serif;}
        .vaz-lang-toggle{display:flex !important;align-items:center;gap:7px;width:auto !important;height:auto !important;min-height:0 !important;padding:9px 14px 9px 10px !important;margin:0 !important;border-radius:999px !important;background:rgba(255,255,255,.96) !important;color:#0F2A48 !important;border:1px solid rgba(15,42,72,.12) !important;font-size:12.5px;font-weight:700;letter-spacing:.03em;line-height:1 !important;cursor:pointer;box-shadow:0 8px 24px rgba(15,42,72,.16);backdrop-filter:blur(6px);transition:transform .2s ease,box-shadow .2s ease;}
        .vaz-lang-toggle:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(15,42,72,.22);}
        .vaz-lang-toggle:focus-visible{outline:2px solid #E9A668;outline-offset:2px;}
        .vaz-lang-toggle .vaz-lang-flag{display:flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;overflow:hidden;flex:none;box-shadow:0 0 0 1.5px #E9A668;}
        .vaz-lang-flag img{width:100% !important;height:100% !important;max-width:none !important;object-fit:cover !important;object-position:center !important;display:block !important;}
        .vaz-lang-menu{position:absolute;bottom:calc(100% + 10px);right:0;background:#fff;border-radius:14px;box-shadow:0 16px 40px rgba(15,42,72,.20);padding:6px;display:none;flex-direction:column;min-width:190px;}
        .vaz-lang-switcher.is-open .vaz-lang-menu{display:flex;}
        .vaz-lang-item{display:flex !important;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;color:#1F1F1F !important;text-decoration:none !important;font-size:13.5px;font-weight:600;white-space:nowrap;transition:background .15s ease;}
        .vaz-lang-item:hover{background:#FBF6EE;}
        .vaz-lang-item.is-current{background:#F7F5F1;}
        .vaz-lang-item.is-current .vaz-lang-name{color:#0F2A48 !important;font-weight:700;}
        .vaz-lang-item .vaz-lang-flag{display:flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;overflow:hidden;flex:none;box-shadow:0 0 0 1px rgba(15,42,72,.10);}
        .vaz-lang-item.is-current .vaz-lang-flag{box-shadow:0 0 0 1.5px #E9A668;}
        .vaz-lang-name{flex:1;}
        .vaz-lang-code{font-size:11px;color:#8a8a8a;font-weight:700;}
    ');

    wp_register_script('vaz-lang-switcher', false, [], null, true);
    wp_enqueue_script('vaz-lang-switcher');
    wp_add_inline_script('vaz-lang-switcher', '
        document.addEventListener("click", function (e) {
            var root = document.querySelector(".vaz-lang-switcher");
            if (!root) return;
            var toggle = root.querySelector(".vaz-lang-toggle");
            if (e.target === toggle || toggle.contains(e.target)) {
                var open = root.classList.toggle("is-open");
                toggle.setAttribute("aria-expanded", open ? "true" : "false");
                return;
            }
            if (!root.contains(e.target)) {
                root.classList.remove("is-open");
                toggle.setAttribute("aria-expanded", "false");
            }
        });
    ');
});
