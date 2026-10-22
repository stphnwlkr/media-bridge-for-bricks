( function () {
	'use strict';
	let region;
	const timers = new Map();
	function placeRegion() {
		const parent = Array.from( document.querySelectorAll( 'dialog[open]' ) ).pop() || document.body;
		if ( region.parentElement !== parent ) parent.append( region );
		if ( region.showPopover && ! region.matches( ':popover-open' ) ) region.showPopover();
	}
	window.uplinkMbbToast = function ( message, type = 'success' ) {
		if ( ! message ) return;
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.className = 'uplink-mbb-toasts';
			region.setAttribute( 'popover', 'manual' );
		}
		placeRegion();
		const enhanced = document.querySelector( '.uplink-mbb-native-wrap' );
		const toast = document.createElement( enhanced && customElements.get( 'wa-callout' ) ? 'wa-callout' : 'div' );
		if ( toast.localName === 'wa-callout' ) {
			toast.setAttribute( 'variant', type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'success' );
			toast.setAttribute( 'appearance', 'outlined' );
			toast.classList.add( 'uplink-mbb-optimization-theme' );
		}
		toast.className += ` uplink-mbb-toast is-${ [ 'success', 'error', 'warning' ].includes( type ) ? type : 'success' }`;
		const text = document.createElement( 'span' );
		text.setAttribute( 'role', type === 'error' ? 'alert' : 'status' );
		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'uplink-mbb-toast-dismiss';
		close.setAttribute( 'aria-label', window.uplinkMbbToastStrings?.dismiss || 'Dismiss notification' );
		close.textContent = '×';
		const dismiss = () => {
			window.clearTimeout( timers.get( toast ) );
			timers.delete( toast );
			toast.remove();
			if ( ! region.childElementCount ) region.remove();
		};
		close.addEventListener( 'click', dismiss );
		toast.append( text, close );
		region.append( toast );
		text.textContent = message;
		if ( type !== 'error' ) timers.set( toast, window.setTimeout( dismiss, 5000 ) );
	};
	// A toast triggered inside a modal must remain clickable above that modal.
	document.addEventListener( 'close', () => { if ( region?.childElementCount ) placeRegion(); }, true );
	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-uplink-mbb-toast]' ).forEach( ( notice ) => {
			window.uplinkMbbToast( notice.textContent.trim(), notice.dataset.uplinkMbbToast );
			notice.remove();
		} );
	} );
}() );
