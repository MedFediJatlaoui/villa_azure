<?php
/* Villa Azur — minimal targeted fixes only, no kit overrides */

// ── FluentForms: calendrier en français ──────────────────────────────────────
add_filter('fluentform/date_i18n', function () {
    return [
        'weekdays' => [
            'shorthand' => ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
            'longhand'  => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
        ],
        'months' => [
            'shorthand' => ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'],
            'longhand'  => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
        ],
        'firstDayOfWeek' => 1,
        'weekAbbreviation' => 'Sem.',
        'rangeSeparator'   => ' au ',
        'scrollTitle'      => 'Défiler pour augmenter la valeur',
        'toggleTitle'      => 'Cliquer pour basculer',
    ];
});

// ── wp_mail : timeout court (évite le blocage 5 min si SMTP injoignable) ─────
add_action('phpmailer_init', function ($m) {
    $m->Timeout = 10; // connexion SMTP max 10 s au lieu des 300 s par défaut
});

// ── Reservation form (#4): honeypot + time-trap spam guard ───────────────────
// No external account/API key needed (unlike reCAPTCHA/hCaptcha/Turnstile),
// so it works immediately at zero cost. Two hidden fields are injected by JS
// when the popup opens (see wp_footer below): `vaz_hp`, which a real visitor
// never sees or fills but a script that blindly fills every input does; and
// `vaz_ts`, a client-side open-time timestamp — a human can't fill 5+
// required fields in under ~2.5s, so a near-instant submission is a strong
// automation signal. `fluentform/validation_errors` is Fluent Forms' own
// filter for exactly this (confirmed in FormValidationService.php: signature
// is ($errors, $formData, $form, $fields)) — returning a non-empty $errors
// entry fails the submission through the SAME path as any other validation
// error, so it surfaces via the fluentform_submission_failed event already
// wired to our error banner below. Scoped to form ID 4 only.
// Note: this stops spam CONTENT, not request-volume flooding — that's a
// hosting/network-level concern (e.g. Cloudflare's free tier), not something
// a form plugin can address.
add_filter('fluentform/validation_errors', function ($errors, $formData, $form) {
    if ((int) $form->id !== 4) {
        return $errors;
    }
    // $formData only carries fields Fluent Forms recognises from this form's
    // own schema — vaz_hp/vaz_ts get silently dropped from it even though
    // they're present in the raw request. Read $_POST['data'] directly
    // instead (the same technique Popup Maker's own FluentForms integration
    // class uses for exactly this reason — confirmed in
    // classes/Integration/Form/FluentForms.php::get_popup_id()).
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- form's own nonce is verified upstream by Fluent Forms before this filter runs
    $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
    parse_str($raw, $extra);

    if (!empty($extra['vaz_hp'])) {
        $errors['vaz_hp'] = ['Une erreur est survenue.'];
        return $errors;
    }
    // Deliberately conservative (1.2s, not the more common 2.5s+): this form
    // has two date fields that require actually opening a calendar and a
    // room-type dropdown that can't be browser-autofilled, so a real guest
    // is never realistically this fast — but a fast typist or someone using
    // autofill for name/phone/email could be, and false-rejecting a genuine
    // booking is worse than letting through an occasional crude bot.
    $ts = isset($extra['vaz_ts']) ? (int) $extra['vaz_ts'] : 0;
    if ($ts > 0 && (round(microtime(true) * 1000) - $ts) < 1200) {
        $errors['vaz_ts'] = ['Une erreur est survenue.'];
    }
    return $errors;
}, 10, 3);

add_action("wp_head", function () { ?>
<style>
/* 1. Logo sizing only */
.ekit-template-content-header .elementor-widget-image img {
  max-height: 68px;
  width: auto;
  display: block;
}
/* 2. Nav no-wrap — prevents line break */
.elementskit-navbar-nav {
  flex-wrap: nowrap !important;
  white-space: nowrap;
}
/* 3. Subtle icon-box card hover — additive only, does not change colours */
.elementor-widget-icon-box .elementor-icon-box-wrapper {
  transition: transform .25s ease, box-shadow .25s ease;
}
.elementor-widget-icon-box:hover .elementor-icon-box-wrapper {
  transform: translateY(-5px);
  box-shadow: 0 12px 32px rgba(0,0,0,.10);
}

/* 4. Remap "info" button type to kit primary orange (kit does not define this type) */
.elementor-button-info,
.elementor-button-info:visited {
  background-color: #E9A668;
  border-color: #E9A668;
  color: #1F1F1F;
}
.elementor-button-info:hover,
.elementor-button-info:focus {
  background-color: #0A1B34;
  border-color: #0A1B34;
  color: #fff;
}
/* 5. "default" transparent button on dark hero sections */
.e-con .elementor-button-default {
  background: transparent;
  border: 2px solid rgba(255,255,255,.85);
  color: #fff;
}
.e-con .elementor-button-default:hover {
  background: #E9A668;
  border-color: #E9A668;
  color: #1F1F1F;
}
/* 6. Header CTA ("Réservez") is not a dark-hero ghost button — it already has its
   own solid background from Elementor. Rule 5 above unintentionally applied its
   transparent/white-border ghost style here too, since both share the
   .elementor-button-default class. Also: the global kit button padding is
   asymmetric (12px top / 24px bottom), which pushed the label off-center
   vertically; Elementor's own per-widget padding control does not emit any CSS
   in this setup (confirmed empirically), so it's corrected here directly. */
.elementor-2174 .elementor-element-9fd5a8e.elementor-button-default,
.elementor-2174 .elementor-element-9fd5a8e.elementor-button-default:hover {
  background: transparent;
  border: none;
  box-shadow: none;
  color: inherit;
}
.elementor-2174 .elementor-element-9fd5a8e .elementor-button {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px 24px;
  border: none;
  outline: none;
  box-shadow: none;
}

/* 7. Mobile header: hamburger — centered logo — reservation button.
   Root cause: a previous attempt reordered/flexed the logo and nav columns
   via `order` + `--flex-grow`, but Elementor's own generated stylesheet sets
   `--width` / `--flex-grow` / `--flex-shrink` on these same elements with a
   HIGHER-specificity selector (it always chains both `.elementor-element`
   AND `.elementor-element-<id>` — 3 classes vs our 2), so those particular
   declarations were silently losing the cascade; only `order` (which
   Elementor never sets) actually took effect. That correctly moved the
   hamburger to the left, but left the logo as a small fixed-width (85px)
   box wherever the now-reordered flexible nav column happened to push it —
   nowhere near center (it landed close to the Réservez button instead).
   Fix: take the logo fully out of the flex flow with absolute centering —
   immune to whatever width the other two columns end up with — and match
   Elementor's own selector specificity (plus !important) on anything we
   still need to override, so it can't be silently out-cascaded again. */
@media (max-width: 767px) {
  .elementor-2174 .elementor-element.elementor-element-09b90be {
    position: absolute !important;
    left: 50% !important;
    top: 50% !important;
    transform: translate(-50%, -50%) !important;
    width: auto !important;
    max-width: none !important;
    --width: auto !important;
    --container-widget-width: auto !important;
    z-index: 2;
  }
  .elementor-2174 .elementor-element.elementor-element-11b25c2 {
    width: auto !important;
    max-width: none !important;
    --container-widget-width: auto !important;
  }
}

/* 7b. Hamburger icon color: ElementsKit Lite exposes a control for the
   toggle BUTTON's background but none for the icon itself, so it ships at a
   hardcoded 50%-opacity black — on the header's light peach background that
   reads as a washed-out grey smudge rather than a deliberate mark. Solid
   brand ink at rest, brand orange on hover — the same dark-text/hover-accent
   pairing already used everywhere else (buttons, headings, icon-box hovers). */
.elementor-2174 .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger-icon {
  background-color: #1F1F1F !important;
}
.elementor-2174 .elementor-element.elementor-element-ecad4bd .ekit-menu-icon {
  color: #1F1F1F !important;
}
.elementor-2174 .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger:hover .elementskit-menu-hamburger-icon {
  background-color: #E9A668 !important;
}
.elementor-2174 .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger:hover > .ekit-menu-icon {
  color: #E9A668 !important;
}

/* 8. Mobile menu backdrop was out of sync with the sliding panel: the
   backdrop faded in over .5s ease while the panel itself takes .6s with a
   slower easing curve, so the gray backdrop finished (and was briefly
   visible on its own) before the menu panel arrived. Matching the timing
   removes that flash. */
@media (max-width: 767px) {
  .ekit-sticky .elementskit-menu-offcanvas-elements::before {
    transition: left .6s cubic-bezier(.6,.1,.68,.53) !important;
  }
}
</style>
<?php }, 5);

// ── Visible breadcrumb trail (#9): Rank Math already emits BreadcrumbList ────
// schema sitewide, but nothing renders the visual trail — this site's header
// is an ElementsKit theme-support template (views/theme-support-header.php),
// not a normal theme header.php, so it ships its own dedicated hook right
// after the header markup (`elementskit/template/after_header`) specifically
// for this kind of insertion. Using that instead of adding an Elementor
// widget to the shared header keeps this change out of the fragile, already
// custom-CSS-patched header layout entirely — pure PHP/CSS, zero risk of
// disturbing the logo-centering / hamburger-color fixes already in place
// there. Skipped on the front page, matching standard breadcrumb convention
// (you're already home).
add_action('elementskit/template/after_header', function () {
    if (is_front_page() || ! function_exists('rank_math_the_breadcrumbs')) {
        return;
    }
    echo '<div class="vaz-breadcrumb-bar">';
    rank_math_the_breadcrumbs();
    echo '</div>';
});
add_action('wp_head', function () { ?>
<style>
.vaz-breadcrumb-bar {
  /* Hidden per client request: Rank Math's BreadcrumbList schema is emitted
     separately in the page's JSON-LD (class-jsonld.php / class-breadcrumbs.php)
     regardless of whether this visual trail renders, so hiding it has no SEO
     impact — it only affects the on-page display. */
  display: none;
  max-width: 1440px;
  margin: 0 auto;
  padding: 14px 24px;
}
.vaz-breadcrumb-bar .rank-math-breadcrumb p {
  margin: 0;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px;
  color: #727272;
}
.vaz-breadcrumb-bar .rank-math-breadcrumb a {
  color: #727272;
  text-decoration: none;
}
.vaz-breadcrumb-bar .rank-math-breadcrumb a:hover {
  color: #E9A668;
}
</style>
<?php }, 5);

// ── Leaflet map popup close button (#11): the Localisation/Contact map
// widgets bleed their orange header to the popup's edges via negative
// margins, but Leaflet's default close "×" is a tiny unstyled Tahoma
// character sitting bare at top:0/right:0 — it reads as broken next to the
// styled popup. Restyled sitewide (not per-widget) so both map instances
// get it and any future one does too.
add_action('wp_head', function () { ?>
<style>
.leaflet-popup-content-wrapper {
  border-radius: 6px;
  box-shadow: 0 6px 20px rgba(0,0,0,.18);
  padding: 0 !important;
  overflow: hidden;
}
.leaflet-popup-content {
  margin: 0 !important;
}
.leaflet-popup-close-button {
  top: 8px !important;
  right: 8px !important;
  width: 22px !important;
  height: 22px !important;
  line-height: 21px !important;
  font-size: 17px !important;
  font-weight: 400 !important;
  color: #1F1F1F !important;
  background: rgba(255,255,255,.6) !important;
  border-radius: 50% !important;
  text-align: center !important;
  transition: background .15s ease, color .15s ease;
}
.leaflet-popup-close-button:hover {
  background: #1F1F1F !important;
  color: #fff !important;
}
</style>
<?php }, 5);

// ── robots.txt (#10): fix WP-Optimize's malformed Disallow line ──────────────
// WP-Optimize's own robots_txt filter (priority 99) builds the path by
// stripping "{scheme}://{host}" from the uploads base URL, but wp_parse_url()
// puts the port in its own ['port'] key, separate from ['host'] — so on a
// non-default port (like :8080 here) the strip misses the port, leaving a
// line like "Disallow: :8080/wp-content/uploads/wpo/...json" with no leading
// slash. That's invalid robots.txt syntax and crawlers ignore it. Hooking
// after WP-Optimize (priority 100) and repairing just that one line in place
// — instead of patching the plugin file — means this survives WP-Optimize
// updates and doesn't touch code we don't own.
add_filter('robots_txt', function ($output) {
    return preg_replace('/^Disallow:\s*:\d+(\/.*)$/m', 'Disallow: $1', $output);
}, 100);

// ── Reservation popup (Popup Maker #3241 / Fluent Forms #4): behaviour fixes ─
// The popup's own settings ship two problems that no amount of CSS can safely
// fix, because Popup Maker recalculates them as inline styles at runtime:
//  1) custom_height is a fixed 380px with scrollable_content off, so the real
//     9-field form (taller than that) gets visually clipped.
//  2) close_on_form_submission_delay is 0, so the popup slams shut the instant
//     Fluent Forms reports success — before a guest can read the confirmation.
// `pum_popup_settings` is Popup Maker's supported filter for exactly this
// (Model/Popup.php calls apply_filters('pum_popup_settings', $settings, $ID)
// before the settings are serialized into the popup's data-popmake attribute),
// so this survives Elementor regeneration and cache clears — it's not reading
// or writing any cached markup.
add_filter('pum_popup_settings', function ($settings, $popup_id) {
    if ((int) $popup_id === 3241) {
        $settings['custom_height_auto']             = true;
        $settings['scrollable_content']             = true;
        $settings['close_on_form_submission_delay'] = '4500';
    }
    return $settings;
}, 10, 2);

// ── Reservation popup: guarantee the flatpickr datepicker library is present ─
// The reservation form is rendered inside the popup at wp_footer, which is too
// late for Fluent Forms' conditional asset loader — so `fluentform-advanced.js`
// (the script that would initialise the date fields) never gets enqueued, and
// the calendar silently fails. Rather than fight that timing, we ship flatpickr
// ourselves (Fluent Forms already bundles it, so no new dependency) and drive
// it from a Popup Maker event below. Enqueued site-wide but it is one small,
// cached asset, and the popup is loadable on any page.
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $base = content_url('plugins/fluentform/assets/libs/flatpickr/');
    wp_enqueue_style('vaz-flatpickr', $base . 'flatpickr.min.css', [], '6.2.4');
    wp_enqueue_script('vaz-flatpickr', $base . 'flatpickr.min.js', [], '6.2.4', true);
});

// ── Reservation popup: premium styling ───────────────────────────────────────
// Scoped entirely to #pum-3241 / #popmake-3241 (Popup Maker's IDs for this one
// popup) and #fluentform_4 (the Fluent Form embedded inside it). The flatpickr
// calendar is scoped via the .vaz-fp class we add per-instance in JS, so nothing
// here touches the Contact page's separate FluentForm (#fluentform_3), any other
// popup, or any other datepicker on the site.
add_action('wp_head', function () { ?>
<style>
/* Overlay: brand-tinted dim instead of generic black */
#pum-3241.pum-overlay {
  background-color: rgba(15, 42, 72, .58) !important;
}

/* Container: card look, generous radius/shadow, no more fixed 380px clipping */
#popmake-3241.pum-container {
  position: relative !important; /* anchors the close button to the CARD, not the full-viewport overlay */
  width: 92% !important;
  max-width: 600px !important;
  height: auto !important;
  min-height: 0 !important; /* in case Popup Maker locked a min-height at open time */
  max-height: 88vh !important;
  overflow: hidden !important; /* container never scrolls itself — only .pum-content does */
  border-radius: 20px !important;
  background: #FFFFFF !important;
  box-shadow: 0 30px 70px -15px rgba(15, 42, 72, .35), 0 0 0 1px rgba(15, 42, 72, .04) !important;
  border: none !important;
}
#popmake-3241 .pum-content.popmake-content {
  padding: 40px 36px 32px !important;
  max-height: 88vh;
  overflow-y: auto; /* the single, sole scroll container */
  scrollbar-width: thin;
  scrollbar-color: #E9A668 transparent; /* Firefox — always-visible cue that more content exists */
}
/* Same cue for WebKit/Chromium — a hairline gold thumb, not a bare OS scrollbar */
#popmake-3241 .pum-content.popmake-content::-webkit-scrollbar { width: 5px; }
#popmake-3241 .pum-content.popmake-content::-webkit-scrollbar-track { background: transparent; }
#popmake-3241 .pum-content.popmake-content::-webkit-scrollbar-thumb {
  background: rgba(233, 166, 104, .55);
  border-radius: 10px;
}

/* Popup title — injected via CSS only; no popup_content edit needed/required */
#popmake-3241 .pum-content::before {
  content: "Réservez votre séjour";
  display: block;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 600;
  font-size: 26px;
  line-height: 1.25;
  color: #1F1F1F;
  margin-bottom: 4px;
}
#popmake-3241 .pum-content .fluentform::before {
  content: "Villa Azur Djerba — réponse sous 24h";
  display: block;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px;
  letter-spacing: .03em;
  text-transform: uppercase;
  color: #727272;
  margin-bottom: 20px;
}
/* Once the submission succeeds we add .vaz-success (see JS below); hide the
   intro heading so only the single confirmation message remains. */
#popmake-3241.vaz-success .pum-content::before,
#popmake-3241.vaz-success .pum-content .fluentform::before {
  display: none;
}

/* Close button. Previously this floated above/outside the white card because
   #popmake-3241 had no `position`, so the absolutely-positioned button
   resolved against the full-viewport overlay instead — fixed above. The
   literal bold "X" glyph is also replaced with two thin drawn lines: a
   slimmer, more minimal mark reads as part of the design instead of a
   generic OS dialog button. */
#popmake-3241 .pum-close.popmake-close {
  top: 20px !important;
  right: 20px !important;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #F7F5F1;
  border: none;
  color: #1F1F1F;
  font-size: 0; /* hide the original "X" text node */
  opacity: 1;
  box-shadow: none;
  transition: background .2s ease, color .2s ease, transform .2s ease;
}
#popmake-3241 .pum-close.popmake-close::before,
#popmake-3241 .pum-close.popmake-close::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 12px;
  height: 1.5px;
  background: currentColor;
  border-radius: 1px;
}
#popmake-3241 .pum-close.popmake-close::before { transform: translate(-50%, -50%) rotate(45deg); }
#popmake-3241 .pum-close.popmake-close::after  { transform: translate(-50%, -50%) rotate(-45deg); }
#popmake-3241 .pum-close.popmake-close:hover {
  background: #E9A668;
  color: #1F1F1F;
  transform: rotate(90deg);
}
#popmake-3241 .pum-close.popmake-close:focus-visible {
  outline: 2px solid #E9A668;
  outline-offset: 2px;
}

/* Field groups / labels */
#popmake-3241 #fluentform_4 .ff-el-group { margin-bottom: 14px; }
#popmake-3241 #fluentform_4 .ff-el-input--label label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #8A8A8A;
}
#popmake-3241 #fluentform_4 .ff-el-is-required label:after { color: #E9A668; }

/* Inputs, selects, textareas, datepickers — white at rest, crisp hairline
   border. The previous version filled every single field with the site's
   cream SECTION-background colour (#FDF3E9), which is a tint meant for large
   page blocks, not form controls: at form-field scale it read as flat and
   low-contrast against both the popup's white card and its own 1px border,
   so nothing looked distinct or premium. White + a slightly firmer neutral
   border restores that contrast; the cream/gold tint is now reserved for the
   focus state only, so it actually signals something. */
#popmake-3241 #fluentform_4 .ff-el-form-control {
  border: 1px solid #DCD7CD !important;
  border-radius: 10px !important;
  background: #FFFFFF !important;
  padding: 11px 16px !important;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14.5px;
  color: #1F1F1F;
  box-shadow: none !important;
  transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
}
#popmake-3241 #fluentform_4 .ff-el-form-control::placeholder { color: #ABABAB; }
#popmake-3241 #fluentform_4 .ff-el-form-control:hover { border-color: #C7C0B2 !important; }
#popmake-3241 #fluentform_4 .ff-el-form-control:focus {
  outline: none;
  background: #FFFDF9 !important;
  border-color: #E9A668 !important;
  box-shadow: 0 0 0 3px rgba(233, 166, 104, .18) !important;
}
/* Custom select chevron (kills default browser arrow / native styling) */
#popmake-3241 #fluentform_4 select.ff-el-form-control {
  cursor: pointer;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  padding-right: 40px !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23727272' d='M6 8L0 2l1.4-1.4L6 5.2 10.6.6 12 2z'/%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 16px center !important;
  background-color: #FFFFFF !important;
}
#popmake-3241 #fluentform_4 textarea.ff-el-form-control { min-height: 72px; resize: vertical; }
#popmake-3241 #fluentform_4 .ff-el-is-error .ff-el-form-control {
  border-color: #A33B2B !important;
}
#popmake-3241 #fluentform_4 .error.text-danger { color: #A33B2B; font-size: 12.5px; margin-top: 4px; }

/* Submit button */
#popmake-3241 #fluentform_4 .ff-btn-submit {
  width: 100%;
  background: #E9A668 !important;
  border: none !important;
  border-radius: 10px !important;
  padding: 15px 24px !important;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #1F1F1F !important;
  box-shadow: none !important;
  cursor: pointer;
  transition: background .25s ease, color .25s ease, transform .15s ease;
}
#popmake-3241 #fluentform_4 .ff-btn-submit:hover {
  background: #0F2A48 !important;
  color: #FFFFFF !important;
}
#popmake-3241 #fluentform_4 .ff-btn-submit:active { transform: scale(.98); }

/* Loading state — driven entirely by Fluent Forms' own .ff-working class
   (added while the AJAX request is in flight), so this needs no JS timers and
   it doubles as the built-in guard against duplicate submissions. */
#popmake-3241 #fluentform_4 .ff-btn-submit.ff-working {
  color: transparent !important;
  pointer-events: none;
  position: relative;
}
#popmake-3241 #fluentform_4 .ff-btn-submit.ff-working::after {
  content: "";
  position: absolute;
  left: 50%;
  top: 50%;
  width: 18px;
  height: 18px;
  margin: -9px 0 0 -9px;
  border-radius: 50%;
  border: 2px solid rgba(31, 31, 31, .25);
  border-top-color: #1F1F1F;
  animation: vaz-pop-spin .7s linear infinite;
}
@keyframes vaz-pop-spin { to { transform: rotate(360deg); } }
@keyframes vaz-fade-in { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

/* THE single success experience: Fluent Forms' own inline message (form is
   hidden via this form's "hide_form" confirmation). No toast, no duplicate.
   Forced border/background/shadow to none — FF's default success-message
   style otherwise draws its own box around it, which read as a small,
   disconnected card floating inside a mostly-empty popup. */
#popmake-3241 .ff-message-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  padding: 12px 8px 4px;
  margin: 0 !important;
  border: none !important;
  background: none !important;
  box-shadow: none !important;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 16px;
  line-height: 1.65;
  color: #1F1F1F;
  animation: vaz-fade-in .4s ease;
}
#popmake-3241 .ff-message-success::before {
  content: "\2713";
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #E9A668;
  color: #FFFFFF;
  font-size: 24px;
  margin: 0 auto 4px;
}
/* The popup was sized to fit the full 9-field form; once it collapses to just
   this message, let the card shrink with it instead of leaving a tall,
   mostly-empty white box (do not use max-height: 88vh's leftover space). */
#popmake-3241.vaz-success.pum-container { max-height: none !important; }
#popmake-3241.vaz-success .pum-content.popmake-content {
  max-height: none;
  padding-top: 56px !important;
  padding-bottom: 48px !important;
}

/* Matching inline ERROR experience (single banner at top of the popup, same
   visual language as the form; user stays in the popup). Injected by JS only
   on a hard submission failure — validation errors keep FF's own inline field
   messages, styled above. */
#popmake-3241 .vaz-error {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  background: #FCEDEA;
  border: 1px solid #E8C4BC;
  border-left: 4px solid #A33B2B;
  border-radius: 10px;
  padding: 14px 16px;
  margin: 0 0 22px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px;
  line-height: 1.55;
  color: #7A2A1E;
  animation: vaz-fade-in .3s ease;
}
#popmake-3241 .vaz-error .vaz-error-icon { flex: none; font-weight: 700; color: #A33B2B; }

/* Premium French datepicker — scoped to the .vaz-fp class we add per instance.
   Appended to document.body (flatpickr's default): its own edge-avoidance
   logic is computed against the viewport, which is what correctly keeps all
   7 weekday columns on screen. Appending it inside .pum-content instead (a
   prior attempt, to dodge the popup's z-index) put it inside a box that
   clips on both axes as soon as either overflow-x/-y isn't `visible` — so
   part of the grid (the Dimanche column) was silently cut off. Simplest
   robust fix: leave it at document.body, lift it above Popup Maker's own
   z-index (1999999999) here, and just close it if the popup scrolls (JS). */
.flatpickr-calendar.vaz-fp {
  /* Root cause of the clipped "Dimanche" column: the MetForm plugin (active
     sitewide, unrelated to this form) ships its own flatpickr CSS that
     redefines .flatpickr-calendar to 280px — and because it's enqueued after
     Fluent Forms' own flatpickr.min.css, its 280px wins the cascade even
     though flatpickr's JS still lays out its day-grid at the real 307.875px.
     Forcing the correct width back, scoped to just this calendar. */
  width: 307.875px !important;
  z-index: 2000000001;
  font-family: 'Plus Jakarta Sans', sans-serif;
  border: none;
  border-radius: 14px;
  box-shadow: 0 18px 45px -12px rgba(15, 42, 72, .35);
  background: #FFFFFF;
}
.flatpickr-calendar.vaz-fp .flatpickr-current-month,
.flatpickr-calendar.vaz-fp .flatpickr-current-month input.cur-year,
.flatpickr-calendar.vaz-fp .flatpickr-monthDropdown-months {
  color: #1F1F1F;
  font-weight: 600;
}
.flatpickr-calendar.vaz-fp .flatpickr-weekday {
  color: #727272;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
}
.flatpickr-calendar.vaz-fp .flatpickr-day {
  color: #1F1F1F;
  border-radius: 8px;
}
.flatpickr-calendar.vaz-fp .flatpickr-day:hover {
  background: #FDF3E9;
  border-color: #FDF3E9;
}
.flatpickr-calendar.vaz-fp .flatpickr-day.today { border-color: #E9A668; }
.flatpickr-calendar.vaz-fp .flatpickr-day.selected,
.flatpickr-calendar.vaz-fp .flatpickr-day.selected:hover {
  background: #E9A668;
  border-color: #E9A668;
  color: #1F1F1F;
}
.flatpickr-calendar.vaz-fp .flatpickr-day.flatpickr-disabled,
.flatpickr-calendar.vaz-fp .flatpickr-day.prevMonthDay,
.flatpickr-calendar.vaz-fp .flatpickr-day.nextMonthDay { color: #C9C9C9; }
.flatpickr-calendar.vaz-fp .flatpickr-months .flatpickr-prev-month:hover svg,
.flatpickr-calendar.vaz-fp .flatpickr-months .flatpickr-next-month:hover svg { fill: #E9A668; }

/* Responsive */
@media (max-width: 640px) {
  #popmake-3241.pum-container { width: 94% !important; border-radius: 16px !important; }
  #popmake-3241 .pum-content.popmake-content { padding: 32px 22px 26px !important; }
  #popmake-3241 .pum-content::before { font-size: 22px; }
  /* No width override here either — same reason as above; flatpickr's fixed
     307.875px comfortably fits even a 94%-wide popup on a 360px+ phone. */
}
</style>
<?php }, 6);

// ── Reservation popup: datepicker init + single success/error experience ─────
// Everything below hangs off documented, reliable events — never DOM polling:
//   • Popup Maker's `pumBeforeOpen` / `pumAfterOpen` (fired on #pum-3241) drive
//     the reset + flatpickr init. Initialising on open (not page load) means the
//     input is visible, so flatpickr computes correct coordinates — that fixes
//     the positioning offset. It also survives cache/Elementor regeneration
//     because it reacts to events rather than to any cached markup.
//   • Fluent Forms' native `fluentform_submission_success` /
//     `fluentform_submission_failed` CustomEvents (detail.config.id === form id)
//     drive the confirmation / error. Guarded to form id 4 so the Contact page
//     form (id 3) is never affected.
// Fluent Forms already blocks duplicate submits itself (window.ff_sumitting_form
// + the disabled .ff-working button), so no extra guard is needed here.
add_action('wp_footer', function () { ?>
<script>
(function () {
  if (typeof window.jQuery === 'undefined') { return; }
  var $ = window.jQuery;
  var FORM_ID = '4';

  // French locale passed straight to flatpickr — no extra l10n file to load.
  var FR = {
    weekdays: {
      shorthand: ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'],
      longhand:  ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']
    },
    months: {
      shorthand: ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'],
      longhand:  ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
    },
    firstDayOfWeek: 1,
    rangeSeparator: ' au ',
    weekAbbreviation: 'Sem.'
  };

  function initDatepickers($popup) {
    if (typeof window.flatpickr !== 'function') { return false; }
    var arrivee = null, depart = null;
    $popup.find('input.ff-el-datepicker').each(function () {
      var input = this;
      // Something else on this page (Elementor ships its own flatpickr and
      // appears to auto-init any [data-type-datepicker] input sitewide, even
      // while it's still hidden inside the closed popup) grabs these inputs
      // before we do. A flatpickr measured while hidden computes a broken,
      // undersized calendar — and since input._flatpickr was already set, our
      // own init used to silently skip and that broken instance won. Always
      // tear down whatever is there and (re)build ours now that the popup is
      // actually visible, so sizing is measured correctly and .vaz-fp/locale
      // are guaranteed to apply.
      if (input._flatpickr) { input._flatpickr.destroy(); }
      window.flatpickr(input, {
        locale: FR,
        dateFormat: input.getAttribute('data-format') || 'd/m/Y',
        disableMobile: true,          // keep the styled French calendar on mobile
        minDate: 'today',             // no arrivals/departures in the past
        onReady: function (sel, str, inst) { inst.calendarContainer.classList.add('vaz-fp'); }
      });
      if (input.id.indexOf('arrivee') > -1) { arrivee = input; }
      if (input.id.indexOf('depart') > -1)  { depart = input; }
    });
    // The calendar is appended to <body> (viewport-relative), so if the guest
    // scrolls the popup's own content while it's open, just close it rather
    // than try to track a moving target — simplest robust fix.
    $popup.find('.pum-content').off('scroll.vazFp').on('scroll.vazFp', function () {
      $popup.find('input.ff-el-datepicker').each(function () {
        if (this._flatpickr) { this._flatpickr.close(); }
      });
    });
    // Departure can't precede arrival — a small premium touch.
    if (arrivee && depart && arrivee._flatpickr && depart._flatpickr) {
      arrivee._flatpickr.set('onChange', function (sel) {
        depart._flatpickr.set('minDate', (sel && sel[0]) ? sel[0] : 'today');
      });
    }
    return true;
  }

  // Spam guard fields — see the fluentform/validation_errors filter in PHP.
  // Re-stamps vaz_ts on every open so the timer always starts from when the
  // guest actually saw the form, not from initial page load.
  function ensureSpamGuardFields($popup) {
    var $form = $popup.find('#fluentform_4');
    if (!$form.length) { return; }
    if (!$form.find('input[name="vaz_hp"]').length) {
      $('<input>', { type: 'text', name: 'vaz_hp', autocomplete: 'off', tabindex: '-1', 'aria-hidden': 'true' })
        .css({ position: 'absolute', left: '-9999px', top: 'auto', width: '1px', height: '1px', opacity: 0 })
        .appendTo($form);
    }
    if (!$form.find('input[name="vaz_ts"]').length) {
      $('<input>', { type: 'hidden', name: 'vaz_ts' }).appendTo($form);
    }
    $form.find('input[name="vaz_ts"]').val(Date.now());
  }

  function clearError() { $('#popmake-3241 .vaz-error').remove(); }

  // Reset a previously-submitted popup back to a fresh form before it reopens,
  // so a returning guest never sees a stale confirmation with no form.
  $(document).on('pumBeforeOpen', '#pum-3241', function () {
    var $popup = $(this);
    if ($popup.hasClass('vaz-success')) {
      $popup.removeClass('vaz-success');
      $popup.find('.ff-message-success').remove();
      var $form = $popup.find('#fluentform_4');
      $form.removeClass('ff_force_hide').show();
      if ($form[0]) { $form[0].reset(); }
      // form.reset() only clears the DOM value — flatpickr keeps its own
      // internal selectedDates/minDate, so clear those too or a returning
      // guest can see the previous stay's dates still highlighted.
      $form.find('input.ff-el-datepicker').each(function () {
        if (this._flatpickr) {
          this._flatpickr.clear();
          if (this.id.indexOf('depart') > -1) { this._flatpickr.set('minDate', 'today'); }
        }
      });
    }
    clearError();
  });

  // Initialise the datepicker once the popup is actually visible.
  $(document).on('pumAfterOpen', '#pum-3241', function () {
    var $popup = $(this);
    ensureSpamGuardFields($popup);
    if (!initDatepickers($popup)) {
      var tries = 0;
      var timer = setInterval(function () {
        if (initDatepickers($popup) || ++tries > 20) { clearInterval(timer); }
      }, 100);
    }
  });

  // Single success experience: reveal FF's inline confirmation, hide our intro
  // heading. FF hides the form; Popup Maker auto-closes after 4.5s (set above).
  document.addEventListener('fluentform_submission_success', function (e) {
    var d = e.detail || {};
    if (!d.config || String(d.config.id) !== FORM_ID) { return; }
    clearError();
    var popup = document.getElementById('popmake-3241');
    if (popup) { popup.classList.add('vaz-success'); }
  });

  // Matching error experience: one elegant inline banner, popup stays open,
  // no technical text. Auto-clears after a while and on the next edit.
  document.addEventListener('fluentform_submission_failed', function (e) {
    var d = e.detail || {};
    if (!d.config || String(d.config.id) !== FORM_ID) { return; }
    clearError();
    var content = document.querySelector('#popmake-3241 .pum-content');
    if (!content) { return; }
    var box = document.createElement('div');
    box.className = 'vaz-error';
    box.setAttribute('role', 'alert');
    var icon = document.createElement('span');
    icon.className = 'vaz-error-icon';
    icon.textContent = '!';
    var msg = document.createElement('span');
    msg.textContent = "Votre demande n'a pas pu être envoyée. Merci de vérifier vos informations et de réessayer, ou de nous joindre directement au +216 75 757 257.";
    box.appendChild(icon);
    box.appendChild(msg);
    content.insertBefore(box, content.firstChild);
    setTimeout(clearError, 9000);
    $('#popmake-3241').one('input change', 'input, select, textarea', clearError);
  });
})();
</script>
<?php }, 20);