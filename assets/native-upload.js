( function ( $, wp ) {
	'use strict';
	const config = window.uplinkMbbNativeUpload;
	if ( ! config ) return;
	let bypassActive = false;
	let previousSetting;
	const frames = new WeakSet();

	function setBypass( enabled ) {
		if ( ! window.cimoSettings ) return;
		if ( enabled && ! bypassActive ) {
			previousSetting = window.cimoSettings.disableOptimization;
			window.cimoSettings.disableOptimization = true;
			bypassActive = true;
		} else if ( ! enabled && bypassActive ) {
			window.cimoSettings.disableOptimization = previousSetting;
			bypassActive = false;
		}
		document.querySelectorAll( '.uplink-mbb-native-keep-originals input' ).forEach( input => { input.checked = bypassActive; } );
	}
	function restore() { setBypass( false ); }

	function addControl( root, frame ) {
		if ( ! root || ! window.cimoSettings || root.querySelector( '.uplink-mbb-native-keep-originals' ) ) return;
		const field = document.createElement( 'div' );
		field.className = 'uplink-mbb-native-keep-originals';
		const label = document.createElement( 'label' );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.checked = bypassActive;
		input.addEventListener( 'change', () => setBypass( input.checked ) );
		label.append( input, document.createTextNode( config.keepOriginals ) );
		const help = document.createElement( 'p' );
		help.className = 'description';
		help.textContent = config.help;
		field.append( label, help );
		root.prepend( field );
		if ( frame?.on && ! frames.has( frame ) ) {
			frames.add( frame );
			frame.on( 'close', restore );
		}
	}

	const inline = wp?.media?.view?.UploaderInline?.prototype;
	if ( inline?.render ) {
		const originalRender = inline.render;
		inline.render = function () {
			const result = originalRender.apply( this, arguments );
			addControl( this.el.querySelector( '.uploader-inline-content' ) || this.el, this.controller );
			return result;
		};
	}
	const uploader = wp?.Uploader?.prototype;
	if ( uploader?.init ) {
		const originalInit = uploader.init;
		uploader.init = function () {
			const result = originalInit.apply( this, arguments );
			this.uploader?.bind?.( 'UploadComplete', restore );
			return result;
		};
	}
	// Closing the native inline uploader should not leave Cimo disabled elsewhere.
	document.addEventListener( 'click', event => {
		if ( event.target instanceof Element && event.target.closest( '.uploader-inline .close, .media-modal-close' ) ) restore();
	}, true );
	window.addEventListener( 'pagehide', restore );
	$( () => {
		addControl( document.querySelector( '#plupload-upload-ui' ) );
		window.uploader?.bind?.( 'UploadComplete', restore );
	} );
}( window.jQuery, window.wp ) );
