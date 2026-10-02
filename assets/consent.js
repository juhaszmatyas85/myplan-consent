/**
 * MyPlan Cookie Consent – front end.
 *
 * The consent defaults and a returning visitor's stored choice are already in
 * the dataLayer (inline script at the top of the head). This file shows the
 * banner when there is no valid choice, handles the dialog, stores decisions,
 * sends the Consent Mode update, and releases blocked scripts and embeds.
 *
 * Public API: window.mpConsent.allowed( cat ), .open(), .onChange( fn ),
 * .whenAllowed( cat, fn ). Event: "mpconsent:change" on document.
 */
( function () {
	'use strict';

	var cfg = window.mpcConfig;

	if ( ! cfg ) {
		return;
	}

	var state = window.mpcStored || null;
	var listeners = [];
	var root, banner, modal, floatBtn, lastFocus;

	/* Storage ------------------------------------------------------------- */

	function uuid() {
		if ( window.crypto && window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}

		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( window.crypto.getRandomValues( new Uint8Array( 1 ) )[ 0 ] & 15 );

			return ( c === 'x' ? r : ( r & 3 ) | 8 ).toString( 16 );
		} );
	}

	function writeCookie( value ) {
		var expires = new Date( Date.now() + cfg.expiryDays * 864e5 ).toUTCString();

		document.cookie = cfg.cookieName + '=' + encodeURIComponent( JSON.stringify( value ) ) +
			';expires=' + expires + ';path=/;SameSite=Lax' + ( location.protocol === 'https:' ? ';Secure' : '' );
	}

	/* Queries ------------------------------------------------------------- */

	function allowed( cat ) {
		if ( cat === 'necessary' ) {
			return true;
		}

		return !! ( state && state.c && state.c[ cat ] );
	}

	// "analytics,marketing" means either one is enough.
	function allowedAny( list ) {
		return String( list || '' ).split( /[\s,|]+/ ).some( function ( cat ) {
			return cat && allowed( cat );
		} );
	}

	/* Release ------------------------------------------------------------- */

	function releaseScripts() {
		var blocked = document.querySelectorAll( 'script[type="text/plain"][data-mpc-consent]' );

		Array.prototype.forEach.call( blocked, function ( old ) {
			if ( ! allowedAny( old.getAttribute( 'data-mpc-consent' ) ) ) {
				return;
			}

			var script = document.createElement( 'script' );

			Array.prototype.forEach.call( old.attributes, function ( attr ) {
				if ( attr.name !== 'type' && attr.name.indexOf( 'data-mpc-' ) !== 0 ) {
					script.setAttribute( attr.name, attr.value );
				}
			} );

			if ( old.getAttribute( 'data-mpc-type' ) ) {
				script.type = old.getAttribute( 'data-mpc-type' );
			}

			// A dynamically inserted external script is async by default;
			// keep the document order the page was written in.
			if ( old.src && ! old.hasAttribute( 'async' ) ) {
				script.async = false;
			}

			if ( ! old.src ) {
				script.text = old.text;
			}

			old.parentNode.replaceChild( script, old );
		} );
	}

	function releaseEmbeds() {
		var embeds = document.querySelectorAll( '[data-mpc-embed]' );

		Array.prototype.forEach.call( embeds, function ( wrap ) {
			if ( ! allowed( wrap.getAttribute( 'data-mpc-consent' ) ) ) {
				return;
			}

			wrap.classList.add( 'is-allowed' );

			Array.prototype.forEach.call( wrap.querySelectorAll( 'iframe[data-mpc-src]' ), function ( frame ) {
				frame.setAttribute( 'src', frame.getAttribute( 'data-mpc-src' ) );
				frame.removeAttribute( 'data-mpc-src' );
			} );
		} );
	}

	/* Withdrawal ---------------------------------------------------------- */

	function deleteCookie( name ) {
		var host = location.hostname;
		var parts = host.split( '.' );
		var domains = [ '', host ];

		// Analytics cookies usually sit on the registrable domain (.example.com).
		for ( var i = parts.length - 2; i >= 0; i-- ) {
			domains.push( '.' + parts.slice( i ).join( '.' ) );
		}

		domains.forEach( function ( domain ) {
			document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/' + ( domain ? ';domain=' + domain : '' );
		} );
	}

	function clearCategory( cat ) {
		var patterns = ( cfg.cleanup && cfg.cleanup[ cat ] ) || [];

		document.cookie.split( ';' ).forEach( function ( pair ) {
			var name = pair.split( '=' )[ 0 ].trim();

			var hit = patterns.some( function ( pattern ) {
				return pattern.slice( -1 ) === '*' ? name.indexOf( pattern.slice( 0, -1 ) ) === 0 : name === pattern;
			} );

			if ( hit ) {
				deleteCookie( name );
			}
		} );
	}

	/* Signals ------------------------------------------------------------- */

	function signal( action ) {
		var choices = state.c;

		if ( typeof window.gtag === 'function' && window.mpcConsentMap ) {
			window.gtag( 'consent', 'update', window.mpcConsentMap( choices ) );
		}

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( { event: 'mpc_consent_update', mpc_consent: choices, mpc_action: action } );

		// WP Consent API (wp-consent-api plugin), for plugins that read it.
		if ( typeof window.wp_set_consent === 'function' ) {
			var api = {
				functional: [ 'functional', 'preferences' ],
				analytics: [ 'statistics', 'statistics-anonymous' ],
				marketing: [ 'marketing' ]
			};

			Object.keys( api ).forEach( function ( cat ) {
				api[ cat ].forEach( function ( apiCat ) {
					window.wp_set_consent( apiCat, choices[ cat ] ? 'allow' : 'deny' );
				} );
			} );
		}

		var detail = { choices: choices, action: action };

		listeners.forEach( function ( fn ) {
			try {
				fn( detail );
			} catch ( e ) {}
		} );

		document.dispatchEvent( new CustomEvent( 'mpconsent:change', { detail: detail } ) );
	}

	function log( action ) {
		if ( ! cfg.logUrl || ! window.fetch ) {
			return;
		}

		window.fetch( cfg.logUrl, {
			method: 'POST',
			keepalive: true,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			// "cid", not "id": CloudPanel's Varnish controller reads a JSON "id"
			// in any request body as a post to purge.
			body: JSON.stringify( {
				cid: state.id,
				version: cfg.version,
				action: action,
				choices: state.c,
				page: location.pathname
			} )
		} ).catch( function () {} );
	}

	/* Decisions ----------------------------------------------------------- */

	function choicesFor( value ) {
		var c = {};

		cfg.categories.forEach( function ( cat ) {
			c[ cat ] = typeof value === 'function' ? !! value( cat ) : !! value;
		} );

		return c;
	}

	function decide( choices, action ) {
		var previous = state && state.c ? state.c : {};

		state = {
			v: cfg.version,
			id: ( state && state.id ) || uuid(),
			ts: Math.floor( Date.now() / 1000 ),
			c: choices
		};

		writeCookie( state );
		signal( action );
		log( action );

		var withdrawn = Object.keys( previous ).filter( function ( cat ) {
			return previous[ cat ] && ! choices[ cat ];
		} );

		hideBanner();
		closeModal();
		showFloat();

		if ( withdrawn.length ) {
			// Scripts that already ran cannot be unloaded: clear what they
			// left and start the page again without them.
			withdrawn.forEach( clearCategory );
			location.reload();

			return;
		}

		releaseScripts();
		releaseEmbeds();
	}

	/* UI ------------------------------------------------------------------ */

	// WCAG 2.2 SC 2.4.11 (Focus Not Obscured): while the banner covers the
	// bottom of the viewport, the page scrolls focused elements clear of it.
	function reserveSpace() {
		var height = banner && ! banner.hidden ? banner.offsetHeight : 0;

		document.documentElement.style.scrollPaddingBottom = height ? height + 16 + 'px' : '';
		document.documentElement.style.setProperty( '--mpc-banner-height', height + 'px' );
	}

	function showBanner() {
		if ( ! banner ) {
			return;
		}

		banner.hidden = false;
		root.classList.add( 'has-banner' );
		reserveSpace();
		window.addEventListener( 'resize', reserveSpace );

		// Screen reader and keyboard users meet the choice first, without
		// the page jumping to the bottom.
		banner.focus( { preventScroll: true } );
	}

	function hideBanner() {
		if ( ! banner ) {
			return;
		}

		banner.hidden = true;
		root.classList.remove( 'has-banner' );
		window.removeEventListener( 'resize', reserveSpace );
		reserveSpace();
	}

	function showFloat() {
		if ( floatBtn && cfg.floating ) {
			floatBtn.hidden = false;
		}
	}

	function syncSwitches() {
		Array.prototype.forEach.call( modal.querySelectorAll( '[data-mpc-cat]' ), function ( input ) {
			input.checked = allowed( input.getAttribute( 'data-mpc-cat' ) );
		} );
	}

	function openModal() {
		if ( ! modal ) {
			return;
		}

		lastFocus = document.activeElement;
		syncSwitches();

		if ( typeof modal.showModal === 'function' ) {
			if ( ! modal.open ) {
				modal.showModal();
			}
		} else {
			modal.setAttribute( 'open', '' );
		}

		var first = modal.querySelector( '[data-mpc-cat]' ) || modal.querySelector( 'button' );

		if ( first ) {
			first.focus();
		}
	}

	function closeModal() {
		if ( ! modal || ! modal.hasAttribute( 'open' ) ) {
			return;
		}

		if ( typeof modal.close === 'function' ) {
			modal.close();
		} else {
			modal.removeAttribute( 'open' );
		}
	}

	function onClick( event ) {
		var target = event.target.closest( '[data-mpc-action], [data-mpc-open], [data-mpc-allow], a[href$="#cookie-settings"], .mpc-open' );

		if ( ! target ) {
			return;
		}

		if ( target.hasAttribute( 'data-mpc-allow' ) ) {
			var extra = target.getAttribute( 'data-mpc-allow' );

			decide( choicesFor( function ( cat ) {
				return cat === extra || allowed( cat );
			} ), 'embed' );

			return;
		}

		var action = target.getAttribute( 'data-mpc-action' );

		if ( ! action ) {
			event.preventDefault();
			openModal();

			return;
		}

		switch ( action ) {
			case 'accept':
				decide( choicesFor( true ), 'accept' );
				break;
			case 'reject':
				decide( choicesFor( false ), 'reject' );
				break;
			case 'settings':
				openModal();
				break;
			case 'save':
				decide( choicesFor( function ( cat ) {
					var input = modal.querySelector( '[data-mpc-cat="' + cat + '"]' );

					return input && input.checked;
				} ), 'custom' );
				break;
			case 'close':
				closeModal();
				break;
		}
	}

	function init() {
		root = document.querySelector( '[data-mpc-root]' );

		if ( ! root ) {
			return;
		}

		banner = root.querySelector( '[data-mpc-banner]' );
		modal = root.querySelector( '[data-mpc-modal]' );
		floatBtn = root.querySelector( '.mpc-float' );

		document.addEventListener( 'click', onClick );

		if ( modal ) {
			// Return focus to whatever opened the dialog.
			modal.addEventListener( 'close', function () {
				if ( lastFocus && lastFocus.focus && document.contains( lastFocus ) ) {
					lastFocus.focus();
				}
			} );

			// A click on the backdrop closes the dialog.
			modal.addEventListener( 'click', function ( event ) {
				if ( event.target === modal ) {
					closeModal();
				}
			} );
		}

		if ( state ) {
			showFloat();
			releaseScripts();
			releaseEmbeds();
		} else if ( cfg.respectGpc && navigator.globalPrivacyControl === true ) {
			// The browser already says "do not track me" (Global Privacy
			// Control). Asking again would be a nag; record the refusal and
			// leave the settings button for anyone who wants to opt in.
			decide( choicesFor( false ), 'gpc' );
		} else {
			showBanner();
		}

		if ( location.hash === '#cookie-settings' ) {
			openModal();
		}
	}

	window.mpConsent = {
		allowed: allowed,
		open: openModal,
		onChange: function ( fn ) {
			listeners.push( fn );
		},
		whenAllowed: function ( cat, fn ) {
			if ( allowed( cat ) ) {
				fn();

				return;
			}

			listeners.push( function handler() {
				if ( allowed( cat ) ) {
					listeners.splice( listeners.indexOf( handler ), 1 );
					fn();
				}
			} );
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
