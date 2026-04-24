/* global remindmiiMerchant */
(function () {
	'use strict';

	var cfg = window.remindmiiMerchant;
	if ( ! cfg ) { return; }

	var portal      = document.querySelector( '[data-remindmii-merchant-portal]' );
	if ( ! portal ) { return; }

	var loadingEl   = portal.querySelector( '[data-merchant-loading]' );
	var noAccessEl  = portal.querySelector( '[data-merchant-no-access]' );
	var mainEl      = portal.querySelector( '[data-merchant-main]' );
	var adsList     = portal.querySelector( '[data-merchant-ads-list]' );
	var adModal     = portal.querySelector( '[data-merchant-ad-modal]' );
	var adForm      = portal.querySelector( '[data-merchant-ad-form]' );
	var formStatus  = portal.querySelector( '[data-merchant-form-status]' );
	var modalTitle  = portal.querySelector( '[data-merchant-modal-title]' );
	var editingIdEl = portal.querySelector( '[data-merchant-editing-id]' );
	var newAdBtn    = portal.querySelector( '[data-merchant-new-ad]' );

	var merchant    = null;
	var ads         = [];

	/* ---- helpers ---- */
	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = String( str );
		return d.innerHTML;
	}

	function show( el ) { el && el.removeAttribute( 'hidden' ); }
	function hide( el ) { el && el.setAttribute( 'hidden', '' ); }

	function api( url, method, body ) {
		var opts = {
			method:  method || 'GET',
			headers: { 'X-WP-Nonce': cfg.restNonce, 'Content-Type': 'application/json' },
		};
		if ( body ) { opts.body = JSON.stringify( body ); }
		return fetch( url, opts ).then( function ( r ) { return r.json(); } );
	}

	/* ---- stats ---- */
	function renderStats() {
		var total       = ads.length;
		var active      = ads.filter( function ( a ) { return a.is_active == 1; } ).length;
		var impressions = ads.reduce( function ( s, a ) { return s + ( +a.impressions || 0 ); }, 0 );
		var clicks      = ads.reduce( function ( s, a ) { return s + ( +a.clicks || 0 ); }, 0 );
		var ctr         = impressions > 0 ? ( ( clicks / impressions ) * 100 ).toFixed( 2 ) + '%' : '0%';

		function set( sel, val ) {
			var el = portal.querySelector( sel );
			if ( el ) { el.textContent = val; }
		}

		set( '[data-merchant-stat-total]', total );
		set( '[data-merchant-stat-active]', active );
		set( '[data-merchant-stat-impressions]', impressions.toLocaleString() );
		set( '[data-merchant-stat-clicks]', clicks.toLocaleString() );
		set( '[data-merchant-stat-ctr]', ctr );
	}

	/* ---- ads list ---- */
	function renderAds() {
		if ( ! adsList ) { return; }
		renderStats();

		if ( ! ads.length ) {
			adsList.innerHTML = '<p class="remindmii-muted">' + esc( cfg.i18n.noAds || 'No ads yet.' ) + '</p>';
			return;
		}

		adsList.innerHTML = ads.map( function ( ad ) {
			var activeClass = ad.is_active == 1 ? 'remindmii-merchant-ad--active' : 'remindmii-merchant-ad--inactive';
			var badgeLabel  = ad.is_active == 1 ? ( cfg.i18n.active || 'Active' ) : ( cfg.i18n.inactive || 'Inactive' );
			var badgeClass  = ad.is_active == 1 ? 'remindmii-badge--success' : 'remindmii-badge--muted';
			var ctr         = ad.impressions > 0 ? ( ( ad.clicks / ad.impressions ) * 100 ).toFixed( 2 ) + '%' : '0%';

			return '<div class="remindmii-merchant-ad ' + activeClass + '" data-ad-id="' + esc( ad.id ) + '">'
				+ '<div class="remindmii-merchant-ad__body">'
				+ '<div class="remindmii-merchant-ad__title-row">'
				+ '<h4 class="remindmii-merchant-ad__title">' + esc( ad.title ) + '</h4>'
				+ '<span class="remindmii-badge ' + badgeClass + '">' + esc( badgeLabel ) + '</span>'
				+ '</div>'
				+ ( ad.description ? '<p class="remindmii-merchant-ad__desc">' + esc( ad.description ) + '</p>' : '' )
				+ '<div class="remindmii-merchant-ad__meta">'
				+ '<span>&#128065; ' + esc( (+ad.impressions || 0).toLocaleString() ) + '</span>'
				+ '<span>&#128432; ' + esc( (+ad.clicks || 0).toLocaleString() ) + '</span>'
				+ '<span>CTR: ' + esc( ctr ) + '</span>'
				+ ( ad.start_date ? '<span>&#128197; ' + esc( ad.start_date ) + '</span>' : '' )
				+ '</div>'
				+ '</div>'
				+ '<div class="remindmii-merchant-ad__actions">'
				+ '<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-edit-ad="' + esc( ad.id ) + '">' + esc( cfg.i18n.edit || 'Edit' ) + '</button>'
				+ '<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-toggle-ad="' + esc( ad.id ) + '">' + esc( ad.is_active == 1 ? ( cfg.i18n.deactivate || 'Deactivate' ) : ( cfg.i18n.activate || 'Activate' ) ) + '</button>'
				+ '<button type="button" class="remindmii-button remindmii-button--danger remindmii-button--small" data-delete-ad="' + esc( ad.id ) + '">' + esc( cfg.i18n.delete || 'Delete' ) + '</button>'
				+ '</div>'
				+ '</div>';
		} ).join( '' );
	}

	/* ---- form ---- */
	function openForm( ad ) {
		if ( ! adForm ) { return; }
		adForm.reset();
		hide( formStatus );
		if ( editingIdEl ) { editingIdEl.value = ad ? String( ad.id ) : ''; }
		if ( modalTitle ) { modalTitle.textContent = ad ? ( cfg.i18n.editAd || 'Edit Ad' ) : ( cfg.i18n.newAd || 'New Ad' ); }

		if ( ad ) {
			var fields = [ 'title', 'description', 'image_url', 'background_color', 'text_color',
				'target_age_min', 'target_age_max', 'cta_text', 'cta_url', 'start_date', 'end_date' ];
			fields.forEach( function ( f ) {
				var el = adForm.querySelector( '[name="' + f + '"]' );
				if ( el ) { el.value = ad[ f ] != null ? ad[ f ] : ''; }
			} );
			var activeEl = adForm.querySelector( '[name="is_active"]' );
			if ( activeEl ) { activeEl.checked = ad.is_active == 1; }
		}

		show( adModal );
	}

	function closeForm() {
		hide( adModal );
	}

	function setStatus( msg, isError ) {
		if ( ! formStatus ) { return; }
		formStatus.textContent = msg;
		formStatus.className   = 'remindmii-merchant-form-status' + ( isError ? ' remindmii-merchant-form-status--error' : '' );
		show( formStatus );
	}

	/* ---- load ---- */
	function init() {
		api( cfg.merchantProfileUrl )
			.then( function ( data ) {
				if ( data.code === 'rest_forbidden' || ( data.code && data.status >= 400 ) ) {
					hide( loadingEl );
					show( noAccessEl );
					return;
				}
				merchant = data;

				var nameEl = portal.querySelector( '[data-merchant-name]' );
				if ( nameEl ) { nameEl.textContent = merchant.name || ''; }

				var logoEl = portal.querySelector( '[data-merchant-logo]' );
				if ( logoEl && merchant.logo_url ) {
					logoEl.innerHTML = '<img src="' + esc( merchant.logo_url ) + '" alt="' + esc( merchant.name ) + '" class="remindmii-merchant-header__logo-img" />';
				} else if ( logoEl ) {
					logoEl.innerHTML = '<span class="remindmii-merchant-header__logo-icon">&#127978;</span>';
				}

				return api( cfg.merchantAdsUrl );
			} )
			.then( function ( data ) {
				if ( ! data ) { return; }
				ads = Array.isArray( data ) ? data : [];
				hide( loadingEl );
				show( mainEl );
				renderAds();
			} )
			.catch( function () {
				hide( loadingEl );
				show( noAccessEl );
			} );
	}

	/* ---- event delegation ---- */
	if ( newAdBtn ) {
		newAdBtn.addEventListener( 'click', function () { openForm( null ); } );
	}

	portal.addEventListener( 'click', function ( e ) {
		var editBtn   = e.target.closest( '[data-edit-ad]' );
		var toggleBtn = e.target.closest( '[data-toggle-ad]' );
		var deleteBtn = e.target.closest( '[data-delete-ad]' );
		var closeBtn  = e.target.closest( '[data-merchant-modal-close]' );

		if ( closeBtn )  { closeForm(); return; }
		if ( e.target === adModal ) { closeForm(); return; }

		if ( editBtn ) {
			var adId = editBtn.getAttribute( 'data-edit-ad' );
			var ad   = ads.find( function ( a ) { return String( a.id ) === adId; } );
			if ( ad ) { openForm( ad ); }
			return;
		}

		if ( toggleBtn ) {
			var tid = toggleBtn.getAttribute( 'data-toggle-ad' );
			api( cfg.merchantAdsUrl + '/' + tid + '/toggle', 'POST' )
				.then( function () {
					return api( cfg.merchantAdsUrl );
				} )
				.then( function ( data ) {
					ads = Array.isArray( data ) ? data : ads;
					renderAds();
				} );
			return;
		}

		if ( deleteBtn ) {
			var did = deleteBtn.getAttribute( 'data-delete-ad' );
			if ( ! window.confirm( cfg.i18n.confirmDelete || 'Delete this ad?' ) ) { return; }
			api( cfg.merchantAdsUrl + '/' + did, 'DELETE' )
				.then( function () {
					ads = ads.filter( function ( a ) { return String( a.id ) !== did; } );
					renderAds();
				} );
			return;
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && adModal && ! adModal.hasAttribute( 'hidden' ) ) { closeForm(); }
	} );

	if ( adForm ) {
		adForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var submitBtn = adForm.querySelector( '[data-merchant-ad-submit]' );
			if ( submitBtn ) { submitBtn.disabled = true; }
			setStatus( cfg.i18n.saving || 'Saving…', false );

			var data = {
				title:            ( adForm.querySelector( '[name="title"]' ) || {} ).value || '',
				description:      ( adForm.querySelector( '[name="description"]' ) || {} ).value || '',
				image_url:        ( adForm.querySelector( '[name="image_url"]' ) || {} ).value || '',
				background_color: ( adForm.querySelector( '[name="background_color"]' ) || {} ).value || '#3B82F6',
				text_color:       ( adForm.querySelector( '[name="text_color"]' ) || {} ).value || '#FFFFFF',
				target_age_min:   parseInt( ( adForm.querySelector( '[name="target_age_min"]' ) || {} ).value ) || 0,
				target_age_max:   parseInt( ( adForm.querySelector( '[name="target_age_max"]' ) || {} ).value ) || 120,
				cta_text:         ( adForm.querySelector( '[name="cta_text"]' ) || {} ).value || 'Se tilbud',
				cta_url:          ( adForm.querySelector( '[name="cta_url"]' ) || {} ).value || '',
				start_date:       ( adForm.querySelector( '[name="start_date"]' ) || {} ).value || '',
				end_date:         ( adForm.querySelector( '[name="end_date"]' ) || {} ).value || '',
				is_active:        ( adForm.querySelector( '[name="is_active"]' ) || {} ).checked ? 1 : 0,
				target_gender:    [ 'all' ],
				target_categories:[ 'all' ],
			};

			var editId = editingIdEl ? editingIdEl.value : '';
			var url    = editId ? cfg.merchantAdsUrl + '/' + editId : cfg.merchantAdsUrl;
			var method = editId ? 'PUT' : 'POST';

			api( url, method, data )
				.then( function ( result ) {
					if ( result.code ) {
						setStatus( result.message || ( cfg.i18n.saveFailed || 'Save failed.' ), true );
						if ( submitBtn ) { submitBtn.disabled = false; }
						return;
					}
					return api( cfg.merchantAdsUrl );
				} )
				.then( function ( data ) {
					if ( ! data ) { return; }
					ads = Array.isArray( data ) ? data : ads;
					renderAds();
					closeForm();
				} )
				.catch( function () {
					setStatus( cfg.i18n.saveFailed || 'Save failed.', true );
					if ( submitBtn ) { submitBtn.disabled = false; }
				} );
		} );
	}

	init();
} )();
