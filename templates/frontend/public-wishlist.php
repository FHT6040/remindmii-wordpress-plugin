<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="remindmii-public-wishlist" data-remindmii-public-wishlist>
	<p class="remindmii-public-wishlist__status" data-remindmii-pw-status>
		<?php echo esc_html__( 'Loading wishlist...', 'remindmii' ); ?>
	</p>
	<div class="remindmii-public-wishlist__content" data-remindmii-pw-content hidden>
		<h2 class="remindmii-public-wishlist__title" data-remindmii-pw-title></h2>
		<p class="remindmii-public-wishlist__desc" data-remindmii-pw-desc></p>
		<ul class="remindmii-public-wishlist__items" data-remindmii-pw-items></ul>
	</div>
</div>
<script>
(function () {
	var root   = document.querySelector('[data-remindmii-public-wishlist]');
	var config = window.remindmiiPublicWishlist || null;

	if ( ! root || ! config || ! config.token ) {
		if ( root ) {
			root.querySelector('[data-remindmii-pw-status]').textContent =
				( config && config.i18n && config.i18n.notFound ) || 'Wishlist not found.';
		}
		return;
	}

	var statusEl  = root.querySelector('[data-remindmii-pw-status]');
	var contentEl = root.querySelector('[data-remindmii-pw-content]');
	var titleEl   = root.querySelector('[data-remindmii-pw-title]');
	var descEl    = root.querySelector('[data-remindmii-pw-desc]');
	var itemsEl   = root.querySelector('[data-remindmii-pw-items]');
	var i18n      = config.i18n || {};

	function esc( v ) {
		return String( v )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	fetch( config.apiUrl )
		.then( function ( r ) { return r.ok ? r.json() : Promise.reject( r.status ); } )
		.then( function ( data ) {
			var wl    = data.wishlist;
			var items = data.items || [];

			if ( titleEl ) { titleEl.textContent = wl.title || ''; }
			if ( descEl  ) { descEl.textContent  = wl.description || ''; }

			if ( itemsEl ) {
				itemsEl.innerHTML = '';

				if ( ! items.length ) {
					itemsEl.innerHTML = '<li class="remindmii-empty">' + esc( i18n.noItems || 'No items yet.' ) + '</li>';
				} else {
					items.forEach( function ( item ) {
						var li = document.createElement( 'li' );
						li.className = 'remindmii-public-item' + ( item.is_purchased ? ' remindmii-public-item--purchased' : '' );

						var priceStr = item.price !== null
							? '<span class="remindmii-public-item__price">' + esc( i18n.priceLabel || 'Price' ) + ': ' + esc( item.price ) + ' ' + esc( item.currency ) + '</span>'
							: '';

						var urlStr = item.url
							? '<a class="remindmii-button remindmii-button--secondary remindmii-button--small" href="' + esc( item.url ) + '" target="_blank" rel="noopener noreferrer">' + esc( i18n.viewLink || 'View' ) + '</a>'
							: '';

						var purchasedBadge = item.is_purchased
							? '<span class="remindmii-badge remindmii-badge--public">' + esc( i18n.purchased || 'Purchased' ) + '</span>'
							: '';

						li.innerHTML =
							'<span class="remindmii-public-item__title">' + esc( item.title ) + '</span>' +
							priceStr +
							purchasedBadge +
							( item.description ? '<p class="remindmii-public-item__desc">' + esc( item.description ) + '</p>' : '' ) +
							urlStr;

						itemsEl.appendChild( li );
					} );
				}
			}

			if ( statusEl  ) { statusEl.hidden  = true; }
			if ( contentEl ) { contentEl.hidden = false; }
		} )
		.catch( function () {
			if ( statusEl ) {
				statusEl.textContent = i18n.notFound || 'Wishlist not found or not public.';
				statusEl.hidden = false;
			}
		} );
}());
</script>
