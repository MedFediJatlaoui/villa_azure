<?php
/* Villa Azur — minimal targeted fixes only, no kit overrides */

// ── Fix WP-Cron / Action Scheduler loopback in Docker ────────────────────────
// WP_HOME/WP_SITEURL are http://localhost:8080 (the host-mapped port, needed
// for real browser access), but Apache INSIDE the container only listens on
// port 80 — so any self-loopback request (WP-Cron's own pseudo-cron spawn,
// and Action Scheduler's async queue-runner dispatch) tries to connect to a
// port nothing is listening on from inside the container, fails silently, and
// leaves scheduled/async actions stuck "pending" forever. Rewriting just the
// loopback URLs' host:port to 127.0.0.1 (no port = 80) fixes both without
// touching WP_HOME/WP_SITEURL themselves.
add_filter('cron_request', function ($request) {
    $request['url'] = preg_replace('#^https?://[^/]+#', 'http://127.0.0.1', $request['url']);
    return $request;
});
add_filter('as_async_request_queue_runner_query_url', function ($url) {
    return preg_replace('#^https?://[^/]+#', 'http://127.0.0.1', $url);
});

// ── FluentForms: send email notifications asynchronously ────────────────────
// Fluent Forms core hardcodes plain email notifications to run SYNCHRONOUSLY
// (see EmentNotificationActions::register(): both 'fluentform/notifying_async_
// email_notifications' and '..._notifications' are forced to __return_false
// at priority 9) — meaning the guest's browser waits for every notification
// email to actually finish sending via SMTP before the "submitted" response
// comes back, which is slow whenever more than one notification is enabled.
// Overriding at a later priority (20 > 9) wins the filter chain and routes
// notifications through Fluent Forms' own Action Scheduler queue instead —
// the submission still returns immediately; the loopback fix above ensures
// that queue actually gets processed rather than sitting pending forever.
add_filter('fluentform/notifying_async_email_notifications', '__return_true', 20);
add_filter('fluentform/notifying_async_notifications', '__return_true', 20);

// ── FluentForms: calendrier dans la langue du visiteur ───────────────────────
// One locale per Polylang language (VAZ_I18N::calendar()) instead of hardcoded
// French, so any other Fluent Forms datepicker on the site (this popup builds
// its own flatpickr instance separately — see the wp_footer script below)
// matches the page's language.
add_filter('fluentform/date_i18n', function () {
    $cal = VAZ_I18N::calendar(VAZ_I18N::current());
    return [
        'weekdays'         => $cal['weekdays'],
        'months'           => $cal['months'],
        'firstDayOfWeek'   => $cal['firstDayOfWeek'],
        'weekAbbreviation' => $cal['weekAbbreviation'],
        'rangeSeparator'   => $cal['rangeSeparator'],
        'scrollTitle'      => $cal['scrollTitle'],
        'toggleTitle'      => $cal['toggleTitle'],
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
/* 0. Sticky header vs. WP admin bar (logged-in only): Sticky Header Effects
   pins the header at `position:fixed; top:0` with no offset for the admin
   bar, which is ALSO fixed at top:0 but with a higher z-index (99999 vs the
   header's 9999) — so for logged-in users the black admin bar simply covers
   the header's top portion instead of sitting above it. Guests (no admin
   bar) are unaffected; this only pushes the header down when the bar is
   present. WP's own admin-bar height is 32px (desktop) / 46px (<783px). */
body.admin-bar .she-header:not(.elementor-sticky) {
  top: 32px !important;
}
@media (max-width: 782px) {
  body.admin-bar .she-header:not(.elementor-sticky) {
    top: 46px !important;
  }
}

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
   in this setup (confirmed empirically), so it's corrected here directly.

   Scoped to .ekit-template-content-header (the wrapper ElementsKit prints
   around whichever header template is active) rather than to .elementor-2174.
   2174 is only the FRENCH header template; the per-language duplicates render
   as .elementor-3671/3672/3673/3674, so an id-scoped rule silently stopped
   applying to them — the label went back to being vertically off-center in
   every language except French. The Elementor element ids INSIDE the templates
   are identical (the duplicates are verbatim copies of the payload), so
   matching on the wrapper covers all five languages and keeps working if the
   templates are ever re-duplicated. Class count is unchanged, so this does not
   alter how the rule competes with Elementor's own generated stylesheet. */
.ekit-template-content-header .elementor-element-9fd5a8e.elementor-button-default,
.ekit-template-content-header .elementor-element-9fd5a8e.elementor-button-default:hover {
  background: transparent;
  border: none;
  box-shadow: none;
  color: inherit;
}
.ekit-template-content-header .elementor-element-9fd5a8e .elementor-button {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px 24px;
  border: none;
  outline: none;
  box-shadow: none;
  /* Never narrower than the label. The widget is authored at a percentage
     width, which resolved to 135px in the mobile/tablet band — fine for
     "Réservez" (61px) but 13px too narrow for the Russian "Забронировать"
     (137px), so the text spilled outside the orange fill at 414-1024px. Sizing
     to content makes the longest of the five translations define the width. At
     desktop this is a no-op: the button already measured 185px, exactly its
     text plus padding. */
  /* !important is required: Elementor emits the widget's authored width from
     its own generated stylesheet at the same 3-class specificity, and that file
     is enqueued after this inline block, so an un-flagged declaration loses the
     tie. Also reset --width, which is the custom property Elementor drives
     element width through. */
  width: auto !important;
  max-width: none !important;
}
.ekit-template-content-header .elementor-element.elementor-element-9fd5a8e {
  width: auto !important;
  --width: auto !important;
  max-width: none !important;
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
  .ekit-template-content-header .elementor-element.elementor-element-09b90be {
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
  .ekit-template-content-header .elementor-element.elementor-element-11b25c2 {
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
.ekit-template-content-header .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger-icon {
  background-color: #1F1F1F !important;
}
.ekit-template-content-header .elementor-element.elementor-element-ecad4bd .ekit-menu-icon {
  color: #1F1F1F !important;
}
.ekit-template-content-header .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger:hover .elementskit-menu-hamburger-icon {
  background-color: #E9A668 !important;
}
.ekit-template-content-header .elementor-element.elementor-element-ecad4bd .elementskit-menu-hamburger:hover > .ekit-menu-icon {
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

/* 9. Long words must break rather than widen the page (multilingual).
   Headings are laid out with the default overflow-wrap:normal, which never
   breaks inside a word. That was harmless in French, but Russian and Polish
   translations contain long unbreakable compounds — e.g. the home page's
   "Креативная Средиземноморская Кухня" — that overflow their box on a phone.
   The heading BOX stayed within bounds (342px of 390px) while the TEXT spilled
   past it, which is why the page's scrollWidth grew to 434px on Russian and
   400px on Polish: the document became horizontally scrollable on mobile and,
   because the layout viewport expanded with it, everything positioned against
   the viewport (the fixed language switcher, the off-canvas overlay, the
   absolutely-centred mobile logo) was thrown off-centre too.
   Verified: adding this took Russian from scrollWidth 434 -> 390 (== the
   viewport). Applied to all languages, not just the affected two, so future
   copy edits in any language can't reintroduce it. */
.ekit-heading--title,
.ekit-heading--subtitle,
.elementor-heading-title,
.elementor-widget-text-editor,
.elementor-icon-box-title,
.elementor-icon-box-description,
.elementor-image-box-title,
.elementor-image-box-description,
.page-content h1,
.page-content h2,
.page-content h3,
.page-content h4,
.page-content p {
  overflow-wrap: break-word;
}

/* ══ 10. Responsive audit fixes ═══════════════════════════════════════════
   Measured with a CDP probe at 320/360/375/390/414/480/768/820/1024/1280/
   1440/1920 on every page. Each block below records the measurement that
   justifies it so the numbers can be re-checked rather than re-guessed. */

/* 10a. HEADER — desktop row overflowed the viewport at 1280 and 1440.
   The three header columns are sized logo 190px + nav 63% + CTA 350px, plus
   48px of row padding. At 1280 that totals 190+806+350+48 = 1394px, i.e. 114px
   of horizontal page scroll (measured: +114 at 1280, +55 at 1440, 0 at 1920
   and 0 at <=1024 where the nav collapses to the hamburger). Percentage and
   fixed widths simply cannot coexist here. Letting the nav column flex and the
   CTA column size to its content removes the overflow at every width without
   changing the visual arrangement. Scoped to >1024px because at or below that
   ElementsKit swaps the inline nav for the off-canvas menu. */
@media (min-width: 1025px) {
  .ekit-template-content-header .elementor-element.elementor-element-84e3c4c {
    width: auto !important;
    --width: auto !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }
}

/* The CTA column hugs its button at EVERY width, declared once. Its authored
   widths (135px mobile / 200px tablet / 350px desktop) are all independent of
   the label, so any language whose button is wider than the authored value
   pushed the button out of the column and off-screen — 48px past the viewport
   in Russian and 9px in Polish at 414-767px. Sizing the column to its content
   with no shrink keeps button and column in lockstep in all five languages.
   Deliberately NOT split across breakpoints: three earlier attempts scattered
   this across the >=1025, <=413 and <=374 blocks and the later block silently
   overrode the earlier one each time. */
.ekit-template-content-header .elementor-element.elementor-element-555d87c {
  width: auto !important;
  --width: auto !important;
  flex: 0 0 auto !important;
}

/* 10b. HEADER — the absolutely-centred mobile logo collided with the CTA
   button on small phones: measured overlap 41px at 320, 21px at 360, 13px at
   375 and 6px at 390 (clean only from 414 up), i.e. broken on the most common
   phone widths — the button visibly sat on top of the wordmark. Centring a
   104px logo cannot clear a 135px button on a 320px screen at all, so both are
   scaled down here just for that range; 414px and wider keep the original
   sizing untouched. */
@media (max-width: 413px) {
  .ekit-template-content-header .elementor-widget-image img {
    max-height: 50px;
  }
  .ekit-template-content-header .elementor-element-9fd5a8e .elementor-button {
    padding: 10px 12px;
    font-size: 11px;
    letter-spacing: .02em;
  }
}
/* 10b-2. HEADER — absolute centring of the logo is abandoned for the whole
   hamburger band (<=1024px) and replaced with ordinary flex spacing.
   Rule 7 centres the logo by taking it out of flow at 50% of the bar. That only
   works if the element to its right has a known, fixed width, and it does not:
   the CTA button must size to its label so no language gets clipped, so its
   width varies from 85px (French) to 185px (Russian). Every attempt to keep the
   centring produced a collision somewhere — measured logo/button overlap of
   7-68px across all five languages at 375-480px once the button was allowed to
   fit its text. Laying the three columns out with the space-between the parent
   already declares removes the entire class of collision at every width and in
   every language. The logo is evenly spaced rather than pixel-centred, which is
   a normal mobile header; nothing else about the design changes. */
@media (max-width: 1024px) {
  .ekit-template-content-header .elementor-element.elementor-element-09b90be {
    position: static !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
  }
  /* In normal flow the columns' authored widths (nav 57 + logo 100 + button 135
     + 28 gutter) do not fit 320px, and they are declared with no shrink, so they
     must be free to size to their content. Only the logo and nav columns may
     shrink — the CTA column is intentionally absent here, since the shared rule
     above pins it to its button's width and re-declaring it as shrinkable in
     this later block silently undid that. */
  .ekit-template-content-header .elementor-element.elementor-element-09b90be,
  .ekit-template-content-header .elementor-element.elementor-element-84e3c4c {
    min-width: 0 !important;
    flex-shrink: 1 !important;
  }
  .ekit-template-content-header .elementor-element.elementor-element-09b90be {
    width: auto !important;
    --width: auto !important;
  }
  /* With the logo back in normal flow it precedes the nav in DOM order, which
     left the hamburger sitting in the MIDDLE of the bar. Reorder to the intended
     hamburger | logo | button arrangement. */
  .ekit-template-content-header .elementor-element.elementor-element-84e3c4c { order: -1 !important; }
  .ekit-template-content-header .elementor-element.elementor-element-09b90be { order: 0 !important; }
  .ekit-template-content-header .elementor-element.elementor-element-555d87c { order: 1 !important; }

  /* The logo itself must never be the thing that gives way: it is the brand
     mark, so it holds a fixed legible height and the flexible nav column absorbs
     any remaining pressure instead. */
  .ekit-template-content-header .elementor-element.elementor-element-09b90be {
    flex-shrink: 0 !important;
  }
  .ekit-template-content-header .elementor-widget-image img {
    width: auto !important;
    height: 50px !important;
    max-height: none !important;
  }
}
@media (max-width: 413px) {
  /* Slightly smaller on the narrowest phones, still clearly legible. */
  .ekit-template-content-header .elementor-widget-image img {
    height: 40px !important;
  }
}

/* 10c. TYPOGRAPHY — the large headings are a fixed 40-45px at every width, so
   on narrow screens a single long word cannot fit its box and rule 9 above is
   forced to break it mid-word ("rencontr/e" at 320px). That happens in French
   too, not only in the longer translations: measured non-fitting words at 320px
   included "rencontre" (215px needed / 208px available), "Méditerranéenne"
   (332/272), "maintenant" (220/208), "Chambres" (226/208) and, in Russian,
   "Средиземноморская" (410/272).
   The clamp caps at 40px, which is at or below every current size in this
   range, so nothing is ever enlarged and the existing size hierarchy holds;
   below that the headings scale with the viewport so words fit naturally and
   word-breaking becomes the last resort it should be. */
@media (max-width: 600px) {
  /* 7.5vw rather than 8vw: the hero's highlighted words are set in an italic
     display serif whose glyphs are noticeably wider than the sans used for the
     rest, so at 8vw the Russian "спокойствием." still ran 7px past its own box
     on a 320px screen. */
  .ekit-heading--title {
    font-size: clamp(22px, 7.5vw, 40px) !important;
    line-height: 1.2;
  }
  h1.section-main-title {
    font-size: clamp(26px, 8.5vw, 45px) !important;
    line-height: 1.2;
  }
  /* The accessibility page is plain post content, so its title comes from the
     theme's page header (h1.entry-title) rather than an Elementor widget and
     was missed by the selectors above — it was the one page still scrolling
     sideways at 320px (+19px, from "Déclaration d’accessibilité" in a 300px
     box). Any future non-Elementor page inherits the fix. */
  .page-header .entry-title,
  h1.entry-title {
    font-size: clamp(24px, 8vw, 36px) !important;
    line-height: 1.25;
  }
}
/* overflow-wrap must NOT be scoped to the same <=600px query as the font-size
   clamp above: German's single-word "Barrierefreiheitserklärung" (27 chars, no
   spaces) still overflowed h1.entry-title at 768px — outside that query, where
   the clamp correctly doesn't apply but wrapping is just as necessary since a
   long-enough unbroken word can overflow at any width. */
.page-header .entry-title,
h1.entry-title {
  overflow-wrap: break-word;
}

/* 10d. SPACING — nested Elementor containers each carry desktop horizontal
   padding that is inherited unchanged onto mobile, and it compounds. Measured
   on the home page at 320px: 24px + 32px + 24px per side = 160px, so an
   icon-box ended up 160px wide inside a 320px viewport — half the screen was
   padding, which is what left 14px body text unable to fit its own words.
   Only nested levels are reduced; the outermost section keeps its gutter so
   content never touches the screen edge. */
@media (max-width: 600px) {
  .elementor .e-con .e-con {
    padding-left: 12px !important;
    padding-right: 12px !important;
  }
  .elementor .e-con .e-con .e-con {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
}

/* 10e. FOOTER — the two fixed floating controls (accessibility toggle bottom
   left, language switcher bottom right) sit in the bottom ~76px of the
   viewport, which is exactly where the footer's bottom bar lands once the page
   is scrolled to the end: measured at 1280x900 the toggle covered the start of
   "© 2026 Villa Azur Djerba…" and the language pill covered the "À Propos"
   link. Giving that bar enough bottom padding lifts its content clear of both,
   so the controls float over empty bar instead of over text. Padding rather
   than JS because the controls are position:fixed and cannot know when the bar
   is on screen. Element id is shared by all five language footers. */
/* 84px, not 76: the accessibility toggle reserves exactly 76px of the viewport
   bottom (20px offset + 56px tall), so 76 made the two edges abut rather than
   clear. The extra 8px keeps a visible gap. */
.elementor-element.elementor-element-7f54d29 {
  padding-bottom: 84px !important;
}

/* 10f. FOOTER — the three footer columns stay side by side through the whole
   tablet band and get crushed: measured at 768px the Navigation column was only
   126px wide, which wrapped "Facebook VillaAzurDjerba" onto two lines hard
   against the right edge and clipped "TripAdvisor — 4.7 ★". Elementor only
   stacks them below its 767px mobile breakpoint, so 768-1024 was left with
   three unusable columns. Letting the row wrap with a sensible flex basis makes
   it fall to two columns and then one as space runs out, instead of shrinking
   past readability. */
@media (min-width: 768px) and (max-width: 1024px) {
  .ekit-template-content-footer .elementor-element.elementor-element-16570f9b {
    flex-wrap: wrap !important;
  }
  .ekit-template-content-footer .elementor-element.elementor-element-dda967b,
  .ekit-template-content-footer .elementor-element.elementor-element-1f3abc45,
  .ekit-template-content-footer .elementor-element.elementor-element-6fa3ed1 {
    width: auto !important;
    --width: auto !important;
    flex: 1 1 260px !important;
    min-width: 260px !important;
  }
}

/* 10g. HEADER — undo rule 10d's generic padding for the header row, and tighten
   its flex gap on small screens.
   Deliberately placed AFTER 10d: 10d's selector (.elementor .e-con .e-con .e-con)
   is four classes, the same as the header selectors here, and CSS breaks ties by
   source order — an earlier block simply lost, which is why the columns kept
   12px of padding per side. Measured effect at 320px in Russian: the three
   columns plus 12px paddings plus the row's authored 24px gaps totalled 315px
   inside a 292px content box, so the row overflowed 9px and flex crushed the nav
   column to 24px. The header row is a bare layout wrapper — its columns need no
   inner padding, and 8px gaps are ample at this size. */
@media (max-width: 1024px) {
  .ekit-template-content-header .e-con.elementor-element.elementor-element-09b90be,
  .ekit-template-content-header .e-con.elementor-element.elementor-element-84e3c4c,
  .ekit-template-content-header .e-con.elementor-element.elementor-element-555d87c {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
  .ekit-template-content-header .elementor-element.elementor-element-bd941eb {
    gap: 8px !important;
  }
}

/* 10h. MOBILE MENU — the open off-canvas panel was rendering UNDERNEATH the
   header. ElementsKit gives the panel z-index 1000, but the sticky header row
   carries 9999, so with the menu open the header bar and its "Réservez" button
   painted on top of the menu, and the panel's own close "X" (z-index 10 within
   the panel) sat directly behind that button — measured close button at
   [263..308] against the button at [172..306], i.e. completely covered, leaving
   no visible way to dismiss the menu. Lifting the panel and its backdrop above
   the header fixes both. Safe when closed: the panel is parked entirely
   off-screen (measured left -390 to -40), so a higher z-index cannot intercept
   anything.

   Raising the panel's own z-index alone is not sufficient and was the first thing
   tried: the panel lives INSIDE the nav column, which is inside the header row's
   stacking context, so its z-index only competes with its siblings in that
   column. The CTA column is a sibling of the nav column carrying z-index 9999,
   so the button's subtree painted above the entire nav subtree regardless — even
   position:fixed cannot escape an ancestor stacking context. The nav column
   itself therefore has to outrank the CTA column. */
.ekit-template-content-header .elementor-element.elementor-element-84e3c4c {
  z-index: 10002 !important;
}
.elementskit-menu-offcanvas-elements {
  z-index: 10001 !important;
}
.elementskit-menu-overlay {
  z-index: 10000 !important;
}

/* 10i. HERO BREADCRUMB — each inner page's hero has a small "Home / Page Name"
   trail built from two gum_heading text widgets in a flex-end row, sized to fit
   only what the French text needed. At 768px specifically that row measures
   ~128px, and flex-shrink:1 (the Elementor default) squeezes both widgets
   below their natural content width to make them fit — text can't compress, so
   it overlaps instead: measured on the Polish rooms page, "Accueil" and
   "Pokoje i Apartamenty" rendered directly on top of each other. The row sits
   over empty hero-image background, so letting its two text children refuse to
   shrink and overflow the row's box to the left is enough — nothing else
   occupies that space. One row-id pair per page (stable across that page's 5
   language duplicates, per the same page, verified: chambres-suites, restaurant,
   galerie, a-propos, localisation, contact all use this exact structure). */
.elementor-element-be66b43 > .elementor-element,
.elementor-element-adacdad > .elementor-element,
.elementor-element-a17606c > .elementor-element,
.elementor-element-70294f2 > .elementor-element,
.elementor-element-515209a > .elementor-element,
.elementor-element-2d69c17 > .elementor-element {
  flex-shrink: 0 !important;
}

/* 10j. HOME "RÉSERVEZ VOTRE SÉJOUR" SECTION — rule 10d's 3-level-deep zeroing
   (".e-con .e-con .e-con") also matches elementor-element-272c79df, which sits
   4 e-con levels deep (2a61bacd > 738587d > 43497b7c > 3b171390 > 272c79df).
   Unlike the icon-box case 10d was written for, none of those four ancestors
   carry any padding of their own (738587d/43497b7c/3b171390 are all
   zero-padding structural wrappers, and 2a61bacd/738587d are explicitly 0 at
   this width too) — 272c79df is the ONLY element in the chain providing a
   side gutter (32px, authored for 601-1024px). Zeroing it at <=600px left the
   label, heading, paragraph and both buttons flush against the screen edge on
   phones (measured: 0px padding at 390px vs. 32px at 601px — the gutter
   simply vanishes below the 10d breakpoint). Restoring the same 32px used
   just above 600px keeps the gutter consistent across the whole mobile range.
   Selector matches 10d's specificity (4 classes) and is placed after it, same
   approach as 10g, so this wins the cascade tie instead of losing to 10d. */
@media (max-width: 600px) {
  .elementor .e-con.elementor-element.elementor-element-272c79df {
    padding-left: 32px !important;
    padding-right: 32px !important;
  }
}

/* 10k. RESTAURANT PAGE "Restaurant à la carte" PHOTO — elementor-element-ac87b1a
   was resized in the editor to 101.825% of its own container (a stray drag past
   the container's edge, not a deliberate bleed — the value has no round number
   behind it), so the widget overflows its parent at every width. Its <img> also
   carries a flat height:500px with no responsive override, unlike every other
   sized element already fixed on this site. At desktop that 500px is a tall
   feature-photo crop inside a ~55%-wide column (rule: --width:55% at >=768px),
   but that column becomes 100% width at <=1024px (rule: --width:100% at
   768-1024px, and no override at all below 768px, so it stays full width down
   to phones too) — the same 500px crop of a 2560x1707 source then eats more
   than half an iPhone's viewport height for a single content photo, and no
   longer scales with the column at all. Same element id across all 5 language
   duplicates of this page (post-2654/3580/3581/3582/3583.css checked directly),
   so one unscoped rule covers all of them. Both sub-rules use !important only
   (no specificity match needed): the page CSS's width/height declarations
   carry no !important of their own, so any !important here wins outright. */
@media (max-width: 1024px) {
  .elementor-element.elementor-element-ac87b1a {
    width: 100% !important;
    max-width: 100% !important;
    --container-widget-width: 100% !important;
  }
}
@media (max-width: 767px) {
  .elementor-element.elementor-element-ac87b1a img {
    height: auto !important;
  }
}

/* 10l. RESTAURANT PAGE PHOTO, continued — fixing 10k's img height to auto
   revealed that the photo's own container, elementor-element-fb3019e, carries
   a flat min-height:574px at every width (no responsive override anywhere in
   the page CSS). At desktop that height was already filled by the old fixed
   500px image plus its wrapper, so it went unnoticed; once the image scales
   down proportionally on a phone (~227px tall at a ~340px-wide column), the
   container still holds itself open to 574px, leaving a large blank gap below
   the photo. Phone-only per request — scoped to the same <=767px breakpoint as
   10k's height fix, not touched at tablet/desktop. Safe against layout shift:
   the <img> keeps its width/height HTML attributes (2560x1707), so browsers
   still reserve its aspect-ratio box before it loads. Same element id across
   all 5 language duplicates, as with 10k. */
@media (max-width: 767px) {
  .elementor-element.elementor-element-fb3019e {
    min-height: 0 !important;
    --min-height: 0px !important;
  }
}

/* 10m. ABOUT PAGE "Notre Mission" / "Notre Vision" — the two image-box widgets
   share the same base style (.elementor-image-box-wrapper{text-align:center}),
   but only elementor-element-5b626a2 ("Notre Mission") has a page-authored
   mobile-breakpoint override knocking it back to text-align:start at
   <=767px — elementor-element-0989806 ("Notre Vision") has no such override
   and stays centered. Two widgets built the same way, one accidentally given
   a different mobile alignment in the editor. Restoring center on Mission
   only, phone-only, so it matches Vision instead of changing Vision. Same
   element id across all 5 language duplicates of this page (post-2658/3588/
   3589/3590/3591.css checked directly). No !important needed on the page's
   own rule to beat — same reasoning as 10k/10l. */
@media (max-width: 767px) {
  .elementor-element.elementor-element-5b626a2 .elementor-image-box-wrapper {
    text-align: center !important;
  }
}

/* 10n. ROOMS PAGE — the decorative circle centred over the 2x2 photo grid
   (elementor-element-9e451e6, a 24px-ring accent shape, no content) is
   150px in diameter at every width from 768px up — that's the designed
   size. Only the <767px rule breaks the pattern: instead of the same or a
   smaller circle for the narrower phone grid, it jumps UP to --width/
   --min-height:250px, nearly 1.7x the desktop size, which is why it swallows
   most of the four photos instead of sitting as a small accent between them.
   Reusing the site's own already-designed 150px for phones too — same value
   already approved at every wider breakpoint, not a new number — and scaling
   the accompanying negative margins (originally -100px/-20px, tuned to pull
   a 250px circle onto the grid intersection) down by the same 150/250 = 0.6
   ratio so a smaller circle lands on the same intersection point rather than
   drifting off it. Border width (24px) and border-radius (100%) are already
   breakpoint-independent, so they're untouched — only diameter and offset
   change. Same element id across all 5 language duplicates of this page
   (post-2617/3576/3577/3578/3579.css checked directly). */
@media (max-width: 767px) {
  .elementor-element.elementor-element-9e451e6 {
    width: 150px !important;
    min-height: 150px !important;
    margin-bottom: -60px !important;
    margin-right: -12px !important;
    --width: 150px !important;
    --min-height: 150px !important;
    --margin-bottom: -60px !important;
    --margin-right: -12px !important;
  }
}

/* 10o. HOME PAGE — same pattern as 10n, different section. The decorative
   pill/blob (elementor-element-2e10aa47, an empty container with a fixed
   100px border-radius rather than 50%, so it's a stadium shape, not a true
   circle) sits between the hero gallery and the "Villa Azur Djerba" facade
   photo. Height is a constant 102px at every width (no breakpoint touches
   --min-height), but width flips the same way 10n's circle did: 122px at
   desktop/tablet (>=768px), then UP to 172px on phones (<767px) instead of
   staying the same or shrinking — the opposite of normal responsive scaling.
   At 122px wide x 102px tall it's a compact rounded-square accent; at 172px
   wide with the same 102px height it stretches into the elongated blob shape
   reported. Restoring the already-designed 122px used at every wider
   breakpoint. Margins/z-index left untouched — those position it between the
   two photos and aren't implicated by the width value itself. Same element
   id across all 5 language duplicates of the home page (post-109/3572/3573/
   3574/3575.css checked directly), matching 10n's approach exactly. */
@media (max-width: 767px) {
  .elementor-element.elementor-element-2e10aa47 {
    width: 122px !important;
    --width: 122px !important;
  }
}

/* 10p. HOME PAGE — the facade-photo/stats-counter row (elementor-element-
   7fb7f239) carries a literal --width:1574px from 768px up, with no upper
   bound and no percentage fallback, unlike every other element in this
   chain (its own parent 5be531a3 is a normal --width:91.889%). Its parent
   has no explicit flex-direction, so it inherits Elementor's own container
   default (.e-con.e-flex{--flex-direction:column}) — meaning 7fb7f239's
   width is a CROSS-axis dimension there, not something flex-shrink can
   reduce to fit. Elementor applies it as a literal `width:var(--width)`
   (checked in elementor/assets/css/frontend.min.css, no clamp/min()), so
   from 768px up this row — and everything sized as a percentage of it,
   including the 2x2 counter grid's calc(50% - 12px) cards — is laid out
   against a 1574px basis instead of the real viewport. On a genuinely wide
   desktop screen that's wide enough it goes unnoticed; at tablet/narrow-
   laptop widths (768-1439px) it isn't, which is why two of the four counter
   cards end up positioned/sized against the wrong basis and the facade
   photo above them is affected too. Scoped to 768-1439px (tablet through
   portrait/narrow laptop) so wide desktop, where this may have been the
   intended value, is untouched — falls back to the container-widget-width
   this element already correctly uses at <=1024px, extended up through
   1439px. Same element id across all 5 language duplicates of the home
   page (post-109/3572/3573/3574/3575.css checked directly). */
@media (min-width: 768px) and (max-width: 1439px) {
  .elementor-element.elementor-element-7fb7f239 {
    width: 100% !important;
    --width: 100% !important;
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
  /* NOT max-height:88vh (that was the original rule) — this element is now a
     flex child (flex:1 1 auto, set below) of a container ALSO capped at
     max-height:88vh, so giving both the same 88vh budget left zero room for
     the .vaz-popup-submit-footer sibling: the footer rendered 52px past the
     bottom of the container's own clipped box, effectively invisible. Flex
     sizing already constrains this element correctly to "whatever the
     container has left after the footer" — it needs no max-height of its own. */
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

/* Popup title — injected via CSS only; no popup_content edit needed/required.
   Localized from VAZ_I18N: these were hardcoded French, so the popup still
   said "Réservez votre séjour" on the EN/DE/PL/RU pages even though every
   field inside it was translated. json_encode gives a correctly quoted and
   escaped CSS string literal for any language. */
#popmake-3241 .pum-content::before {
  content: <?php echo wp_json_encode(VAZ_I18N::t(VAZ_I18N::current(), 'popup.title'), JSON_UNESCAPED_UNICODE); ?>;
  display: block;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 600;
  font-size: 26px;
  line-height: 1.25;
  color: #1F1F1F;
  margin-bottom: 4px;
}
#popmake-3241 .pum-content .fluentform::before {
  content: <?php echo wp_json_encode(VAZ_I18N::t(VAZ_I18N::current(), 'popup.subtitle'), JSON_UNESCAPED_UNICODE); ?>;
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

/* Submit button.
   Scoped to #popmake-3241 only — deliberately NOT #fluentform_4 too. The JS
   below moves .ff_submit_btn_wrapper out of the <form id="fluentform_4"> into
   a sibling footer div, so the button is no longer a descendant of that form
   ID at all. Every rule here used to read "#popmake-3241 #fluentform_4
   .ff-btn-submit" and silently stopped matching the moment that move ran —
   width:100% (and everything else below: colours, hover, the loading spinner)
   stopped applying, which is why the button visibly shrank to its text's
   natural width instead of filling the footer. */
#popmake-3241 .ff-btn-submit {
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
#popmake-3241 .ff-btn-submit:hover {
  background: #0F2A48 !important;
  color: #FFFFFF !important;
}
#popmake-3241 .ff-btn-submit:active { transform: scale(.98); }

/* Submit button as a true fixed footer, structurally outside the scrollable
   form area — NOT position:sticky.
   Reported: the button was "sometimes cut off, sometimes fully shown,
   sometimes not visible at all" while scrolling the popup on mobile. First
   fix attempt used `position:sticky; bottom:0` directly on the button's
   wrapper, still inside the scrolling `.pum-content`. That created a WORSE,
   visible bug on desktop: the reservation form is 1150-1700px tall (more with
   extra rooms) but the popup's own visible height is ~792px, so the sticky
   wrapper — whose natural document position is far below the fold — activates
   immediately at scrollTop 0. It then renders pinned near the bottom of the
   visible area while EARLIER, unrelated fields (the room adults/children
   counters) are simultaneously laid out at that same on-screen position in
   normal flow, since sticky only affects the element's own box, not its
   siblings. The two visually collided: the submit button appeared to cut
   straight through the middle of the room configuration section instead of
   sitting below it. Sticky-within-a-tall-scroll-container cannot be fixed by
   adjusting its own CSS — the element has to be genuinely OUTSIDE the
   scrolling box. The script below moves `.ff_submit_btn_wrapper` out of
   `.pum-content` into a dedicated sibling footer once, on page load (the
   popup's markup exists in the DOM from first render, only hidden — see
   vaz_popup_move_submit_footer() near the other popup script). `.pum-container`
   becomes a flex column so the (still scrollable, unchanged) `.pum-content`
   fills the remaining space and this footer keeps a fixed, non-scrolling
   height at the very bottom — the only way to guarantee no overlap with
   content above it, at ANY content height or viewport height. */
#popmake-3241.pum-container {
  /* !important required: Popup Maker's own positioning script sets
     `style="display: block; ..."` directly on this element's inline style
     attribute (alongside JS-computed top/left offsets), which otherwise wins
     over any un-flagged rule regardless of selector specificity. */
  display: flex !important;
  flex-direction: column;
}
#popmake-3241 .pum-content.popmake-content {
  flex: 1 1 auto;
  min-height: 0; /* required for a flex child to be allowed to shrink below its content's height and actually scroll */
}
#popmake-3241 .vaz-popup-submit-footer {
  flex: 0 0 auto;
  background: #FFFFFF;
  padding: 12px 36px 24px; /* 36px matches .pum-content's own horizontal padding, so the button lines up with the fields above it */
  box-shadow: 0 -8px 12px -4px rgba(0, 0, 0, .06);
}
#popmake-3241 .vaz-popup-submit-footer .ff_submit_btn_wrapper {
  margin-bottom: 0 !important;
}
@media (max-width: 413px) {
  #popmake-3241 .vaz-popup-submit-footer { padding-left: 22px; padding-right: 22px; }
}

/* Loading state — driven entirely by Fluent Forms' own .ff-working class
   (added while the AJAX request is in flight), so this needs no JS timers and
   it doubles as the built-in guard against duplicate submissions. */
#popmake-3241 .ff-btn-submit.ff-working {
  color: transparent !important;
  pointer-events: none;
  position: relative;
}
#popmake-3241 .ff-btn-submit.ff-working::after {
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

  // Move the submit button out of the scrollable form into its own fixed
  // footer (paired with the flex-column CSS on #popmake-3241 above). Doing
  // this with `position:sticky` on the button in place instead — the first
  // fix attempted — broke worse than the original bug: sticky only affects
  // the element's own box, not the layout of anything else, so on desktop
  // (short popup, tall multi-room form) the button rendered pinned partway
  // down the visible area while the room count fields — earlier in the
  // document, laid out completely normally — occupied that exact same screen
  // position underneath it. A structural move is the only way to guarantee no
  // overlap regardless of form/viewport height. Runs once, immediately: Popup
  // Maker renders this popup's markup in the page from first load (just
  // hidden), it does not need the popup to be open first.
  (function moveSubmitButtonToFooter() {
    var popup = document.getElementById('popmake-3241');
    if (!popup) { return; }
    var wrapper = popup.querySelector('.ff_submit_btn_wrapper');
    var container = popup.querySelector('.pum-content');
    if (!wrapper || !container || wrapper.parentElement.classList.contains('vaz-popup-submit-footer')) { return; }
    var footer = document.createElement('div');
    footer.className = 'vaz-popup-submit-footer';
    footer.appendChild(wrapper);
    popup.appendChild(footer);
  })();

  // Locale passed straight to flatpickr, in the guest's Polylang language —
  // shared with vaz-reservation.js via window.VAZ_RESA.calendar (localized
  // from VAZ_I18N::calendar() in PHP), so there's one source of truth per
  // language rather than a second hardcoded copy here. Falls back to French
  // if VAZ_RESA hasn't loaded for some reason.
  var FR = (window.VAZ_RESA && window.VAZ_RESA.calendar) || {
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