<?php
/**
 * Plugin Name: Villa Azur - Accessibilite
 * Description: Widget d'accessibilite auto-heberge (taille du texte, contraste, police dyslexie, reduction des animations, curseur agrandi). Aucun compte externe, aucun cout.
 * Version: 1.3.0
 * Author: Villa Azur
 * Text Domain: villa-a11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VILLA_A11Y_VERSION', '1.3.0' );
define( 'VILLA_A11Y_URL', plugin_dir_url( __FILE__ ) );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'villa-a11y', VILLA_A11Y_URL . 'assets/css/widget.css', array(), VILLA_A11Y_VERSION );
	wp_enqueue_script( 'villa-a11y', VILLA_A11Y_URL . 'assets/js/widget.js', array(), VILLA_A11Y_VERSION, true );
} );

/**
 * Re-apply saved preferences before first paint to avoid a flash of
 * unstyled content when a returning visitor already has settings saved.
 */
add_action( 'wp_head', function () {
	?>
	<script>
	(function(){
		try {
			var s = JSON.parse( localStorage.getItem( 'villaA11yPrefs' ) || '{}' );
			var cl = document.documentElement.classList;
			if ( s.fontStep ) { cl.add( s.fontStep < 0 ? 'villa-a11y-fs-dec' + Math.abs( s.fontStep ) : 'villa-a11y-fs-' + s.fontStep ); }
			if ( s.contrast ) { cl.add( 'villa-a11y-contrast' ); }
			if ( s.dyslexia ) { cl.add( 'villa-a11y-dyslexia' ); }
			if ( s.motion ) { cl.add( 'villa-a11y-reduce-motion' ); }
			if ( s.cursor ) { cl.add( 'villa-a11y-big-cursor' ); }
		} catch (e) {}
	})();
	</script>
	<?php
}, 1 );

add_action( 'wp_footer', function () {
	?>
	<button type="button" id="villa-a11y-toggle" class="villa-a11y-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="villa-a11y-panel" aria-label="<?php esc_attr_e( "Ouvrir les options d'accessibilite", 'villa-a11y' ); ?>">
		<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true" focusable="false">
			<circle cx="12" cy="4" r="2"></circle>
			<path d="M12 8c-3.9 0-7.5.7-7.5 2.1 0 .8 1.2 1.4 3 1.8v9.6a1.5 1.5 0 003 0v-6h3v6a1.5 1.5 0 003 0v-9.6c1.8-.4 3-1 3-1.8C19.5 8.7 15.9 8 12 8z"></path>
		</svg>
	</button>

	<div id="villa-a11y-panel" class="villa-a11y-panel" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( "Options d'accessibilite", 'villa-a11y' ); ?>" hidden>
		<div class="villa-a11y-panel__header">
			<h2><?php esc_html_e( 'Accessibilite', 'villa-a11y' ); ?></h2>
			<button type="button" id="villa-a11y-close" aria-label="<?php esc_attr_e( 'Fermer', 'villa-a11y' ); ?>">&times;</button>
		</div>

		<div class="villa-a11y-group">
			<span class="villa-a11y-group__label"><?php esc_html_e( 'Taille du texte', 'villa-a11y' ); ?></span>
			<div class="villa-a11y-row">
				<button type="button" data-action="font-dec" aria-label="<?php esc_attr_e( 'Diminuer le texte', 'villa-a11y' ); ?>">A-</button>
				<button type="button" data-action="font-reset" aria-label="<?php esc_attr_e( 'Taille par defaut', 'villa-a11y' ); ?>">A</button>
				<button type="button" data-action="font-inc" aria-label="<?php esc_attr_e( 'Augmenter le texte', 'villa-a11y' ); ?>">A+</button>
			</div>
		</div>

		<button type="button" class="villa-a11y-toggle-row" data-toggle="contrast">
			<span><?php esc_html_e( 'Contraste eleve', 'villa-a11y' ); ?></span>
			<span class="villa-a11y-switch" aria-hidden="true"></span>
		</button>

		<button type="button" class="villa-a11y-toggle-row" data-toggle="dyslexia">
			<span><?php esc_html_e( 'Lecture facile (dyslexie)', 'villa-a11y' ); ?></span>
			<span class="villa-a11y-switch" aria-hidden="true"></span>
		</button>

		<button type="button" class="villa-a11y-toggle-row" data-toggle="motion">
			<span><?php esc_html_e( 'Reduire les animations', 'villa-a11y' ); ?></span>
			<span class="villa-a11y-switch" aria-hidden="true"></span>
		</button>

		<button type="button" class="villa-a11y-toggle-row" data-toggle="cursor">
			<span><?php esc_html_e( 'Curseur agrandi', 'villa-a11y' ); ?></span>
			<span class="villa-a11y-switch" aria-hidden="true"></span>
		</button>

		<button type="button" id="villa-a11y-reset" class="villa-a11y-reset-all"><?php esc_html_e( 'Reinitialiser', 'villa-a11y' ); ?></button>

		<a class="villa-a11y-statement-link" href="<?php echo esc_url( home_url( '/accessibilite/' ) ); ?>"><?php esc_html_e( "Declaration d'accessibilite", 'villa-a11y' ); ?></a>
	</div>
	<?php
} );
