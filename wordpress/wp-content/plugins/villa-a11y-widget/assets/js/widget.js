(function () {
	'use strict';

	var STORAGE_KEY = 'villaA11yPrefs';
	var MAX_FONT_STEP = 4;
	var MIN_FONT_STEP = -2;
	var html = document.documentElement;

	function fontStepClass( step ) {
		return step < 0 ? 'villa-a11y-fs-dec' + Math.abs( step ) : 'villa-a11y-fs-' + step;
	}

	function loadPrefs() {
		try {
			return JSON.parse( localStorage.getItem( STORAGE_KEY ) || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function savePrefs( prefs ) {
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( prefs ) );
		} catch ( e ) {}
	}

	var prefs = loadPrefs();

	function clearFontClasses() {
		for ( var i = MIN_FONT_STEP; i <= MAX_FONT_STEP; i++ ) {
			if ( i !== 0 ) {
				html.classList.remove( fontStepClass( i ) );
			}
		}
	}

	function applyFontStep( step ) {
		clearFontClasses();
		if ( step !== 0 ) {
			html.classList.add( fontStepClass( step ) );
		}
		prefs.fontStep = step;
		savePrefs( prefs );
	}

	function pauseMedia() {
		document.querySelectorAll( 'video, audio' ).forEach( function ( el ) {
			if ( ! el.paused ) {
				el.dataset.villaA11yWasPlaying = 'true';
				el.pause();
			}
		} );
		if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.swiper ) {
			try {
				window.jQuery( '.swiper' ).each( function () {
					var instance = this.swiper;
					if ( instance && instance.autoplay ) {
						instance.autoplay.stop();
					}
				} );
			} catch ( e ) {}
		}
		document.querySelectorAll( '.swiper' ).forEach( function ( el ) {
			if ( el.swiper && el.swiper.autoplay ) {
				try { el.swiper.autoplay.stop(); } catch ( e ) {}
			}
		} );
	}

	function resumeMedia() {
		document.querySelectorAll( '[data-villa-a11y-was-playing="true"]' ).forEach( function ( el ) {
			el.play();
			delete el.dataset.villaA11yWasPlaying;
		} );
		document.querySelectorAll( '.swiper' ).forEach( function ( el ) {
			if ( el.swiper && el.swiper.autoplay ) {
				try { el.swiper.autoplay.start(); } catch ( e ) {}
			}
		} );
	}

	function setToggle( key, on ) {
		var className = 'villa-a11y-' + ( key === 'contrast' ? 'contrast' : key === 'dyslexia' ? 'dyslexia' : key === 'motion' ? 'reduce-motion' : 'big-cursor' );
		html.classList.toggle( className, on );
		prefs[ key ] = on;
		savePrefs( prefs );

		if ( key === 'motion' ) {
			if ( on ) {
				pauseMedia();
			} else {
				resumeMedia();
			}
		}
	}

	function syncButtonState( button, key ) {
		var on = !! prefs[ key ];
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggleBtn = document.getElementById( 'villa-a11y-toggle' );
		var panel = document.getElementById( 'villa-a11y-panel' );
		var closeBtn = document.getElementById( 'villa-a11y-close' );
		var resetBtn = document.getElementById( 'villa-a11y-reset' );

		if ( ! toggleBtn || ! panel ) {
			return;
		}

		// Restore control states to match classes already applied in <head>.
		panel.querySelectorAll( '[data-toggle]' ).forEach( function ( button ) {
			syncButtonState( button, button.getAttribute( 'data-toggle' ) );
			button.addEventListener( 'click', function () {
				var key = button.getAttribute( 'data-toggle' );
				var next = ! ( prefs[ key ] );
				setToggle( key, next );
				syncButtonState( button, key );
			} );
		} );

		if ( prefs.motion ) {
			pauseMedia();
		}

		panel.querySelectorAll( '[data-action]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var action = button.getAttribute( 'data-action' );
				var step = prefs.fontStep || 0;
				if ( action === 'font-inc' ) {
					step = Math.min( MAX_FONT_STEP, step + 1 );
				} else if ( action === 'font-dec' ) {
					step = Math.max( MIN_FONT_STEP, step - 1 );
				} else {
					step = 0;
				}
				applyFontStep( step );
			} );
		} );

		function openPanel() {
			panel.hidden = false;
			toggleBtn.setAttribute( 'aria-expanded', 'true' );
			var firstFocusable = panel.querySelector( 'button, a' );
			if ( firstFocusable ) {
				firstFocusable.focus();
			}
		}

		function closePanel() {
			panel.hidden = true;
			toggleBtn.setAttribute( 'aria-expanded', 'false' );
			toggleBtn.focus();
		}

		toggleBtn.addEventListener( 'click', function () {
			if ( panel.hidden ) {
				openPanel();
			} else {
				closePanel();
			}
		} );

		closeBtn.addEventListener( 'click', closePanel );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! panel.hidden ) {
				closePanel();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! panel.hidden && ! panel.contains( e.target ) && e.target !== toggleBtn && ! toggleBtn.contains( e.target ) ) {
				closePanel();
			}
		} );

		resetBtn.addEventListener( 'click', function () {
			prefs = {};
			savePrefs( prefs );
			clearFontClasses();
			html.classList.remove( 'villa-a11y-contrast', 'villa-a11y-dyslexia', 'villa-a11y-reduce-motion', 'villa-a11y-big-cursor' );
			resumeMedia();
			panel.querySelectorAll( '[data-toggle]' ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', 'false' );
			} );
		} );
	} );
})();
