/* global remindmiiLogin */
( function () {
	'use strict';

	var cfg = window.remindmiiLogin || {};

	var endpoints = {
		login:          cfg.loginUrl,
		register:       cfg.registerUrl,
		lost_password:  cfg.lostPasswordUrl,
		reset_password: cfg.resetPasswordUrl,
	};

	document.querySelectorAll( '.remindmii-login-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			var actionField = form.querySelector( '[name="remindmii_login_action"]' );
			if ( ! actionField || ! endpoints[ actionField.value ] ) {
				return; // Let native submit handle unknown actions.
			}
			e.preventDefault();
			handleSubmit( form, actionField.value );
		} );
	} );

	function handleSubmit( form, action ) {
		var url    = endpoints[ action ];
		var btn    = form.querySelector( 'button[type="submit"]' );
		var notice = getOrCreateNotice( form );
		var data   = new URLSearchParams( new FormData( form ) );

		clearNotice( notice );
		setLoading( btn, true );

		fetch( url, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-WP-Nonce':   cfg.nonce || '',
			},
			body: data.toString(),
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( res ) {
			setLoading( btn, false );
			if ( res.success ) {
				if ( res.redirect ) {
					window.location.href = res.redirect;
				} else if ( res.message ) {
					showNotice( notice, res.message, 'success' );
				}
			} else {
				showNotice( notice, res.message || ( cfg.i18n && cfg.i18n.genericError ) || 'Error', 'error' );
			}
		} )
		.catch( function () {
			setLoading( btn, false );
			showNotice( notice, ( cfg.i18n && cfg.i18n.genericError ) || 'Error', 'error' );
		} );
	}

	function getOrCreateNotice( form ) {
		var el = form.querySelector( '.remindmii-login-notice--js' );
		if ( ! el ) {
			el = document.createElement( 'p' );
			el.className = 'remindmii-login-notice remindmii-login-notice--js';
			el.setAttribute( 'role', 'alert' );
			el.style.display = 'none';
			form.insertBefore( el, form.firstChild );
		}
		return el;
	}

	function clearNotice( el ) {
		el.textContent = '';
		el.style.display = 'none';
		el.classList.remove( 'remindmii-login-notice--error', 'remindmii-login-notice--success' );
	}

	function showNotice( el, message, type ) {
		el.textContent = message;
		el.classList.add( 'remindmii-login-notice--' + type );
		el.style.display = '';
	}

	function setLoading( btn, loading ) {
		if ( ! btn ) return;
		btn.disabled = loading;
	}
} )();
