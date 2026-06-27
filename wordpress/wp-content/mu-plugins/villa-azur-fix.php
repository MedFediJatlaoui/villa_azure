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

add_action("wp_head", function () { ?>
<style>
/* 1. Logo sizing only */
.ekit-template-content-header .elementor-widget-image img {
  max-height: 52px;
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
</style>
<?php }, 5);