( function () {
	'use strict';
	const config = window.uplinkMbbCimoConfig;
	let busy = false;
	async function json( url, options ) {
		const response = await fetch( url, { credentials: 'same-origin', ...options } );
		const body = await response.json();
		if ( ! response.ok || body.success === false ) throw new Error( body.message || body.data?.message || config.strings.failed );
		return body;
	}
	// Cimo Premium's smart optimizer accepts a converter with file, options and convert().
	// It controls image comparison and quality selection; this adapter preserves format
	// and pixel dimensions instead of using the pre-upload WebP conversion defaults.
	async function optimizeSize( file ) {
		const source = URL.createObjectURL( file );
		const image = new Image();
		try {
			image.src = source;
			await image.decode();
			const converter = {
				file, options: { format: file.type },
				async convert( { quality = 0.8 } = {} ) {
					const canvas = document.createElement( 'canvas' );
					canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
					canvas.getContext( '2d' ).drawImage( image, 0, 0 );
					// PNG needs palette compression; canvas quality has no effect on PNG.
					const blob = file.type === 'image/png'
						? await window.imageCompression( file, { useWebWorker: false, alwaysKeepResolution: true, maxIteration: 5, fileType: file.type } )
						: await new Promise( resolve => canvas.toBlob( resolve, file.type, quality ) );
					if ( blob && file.type === 'image/png' ) {
						const check = await createImageBitmap( blob );
						const sameDimensions = check.width === image.naturalWidth && check.height === image.naturalHeight;
						check.close();
						if ( ! sameDimensions ) return { file, error: true, reason: 'dimensions-changed' };
					}
					if ( ! blob || blob.type !== file.type ) return { file, error: true, reason: 'format-not-supported' };
					if ( blob.size >= file.size ) return { file, error: true, reason: 'resulting-media-bigger-than-input' };
					return { file: new File( [ blob ], file.name, { type: file.type } ), metadata: {
						originalFormat: file.type, convertedFormat: file.type, originalFilesize: file.size,
						convertedFilesize: blob.size, compressionSavings: blob.size / file.size,
					} };
				},
			};
			return await wp.hooks.applyFiltersAsync( 'cimo.imageConverter.optimize', { file, metadata: null, reason: 'no-optimizer' }, converter );
		} finally { URL.revokeObjectURL( source ); }
	}
	async function animatedPng( file ) {
		if ( file.type !== 'image/png' ) return false;
		const view = new DataView( await file.arrayBuffer() );
		for ( let offset = 8; offset + 12 <= view.byteLength; ) {
			const length = view.getUint32( offset );
			const type = view.getUint32( offset + 4 );
			if ( type === 0x6163544c ) return true; // acTL animation control.
			if ( type === 0x49444154 || type === 0x49454e44 ) break; // IDAT or IEND.
			offset += 12 + length;
		}
		return false;
	}
	async function optimize( id, progress ) {
		if ( busy ) throw new Error( config.strings.failed );
		if ( ! window.wp?.hooks?.hasFilter( 'cimo.imageConverter.optimize', 'cimo.premium' ) ) throw new Error( config.strings.unavailable );
		busy = true;
		const counts = { optimized: 0, unchanged: 0, failed: 0 };
		const errors = [];
		try {
			const form = new FormData();
			form.append( 'action', 'uplink_mbb_cimo_plan' ); form.append( 'nonce', config.nonce ); form.append( 'attachment_id', id );
			const { data: sizes } = await json( config.ajaxUrl, { method: 'POST', body: form } );
			for ( const [ index, size ] of sizes.entries() ) {
				progress( config.strings.progress.replace( '%1$d', index + 1 ).replace( '%2$d', sizes.length ) );
				try {
					const response = await fetch( size.url, { credentials: 'same-origin', cache: 'no-store' } );
					if ( ! response.ok ) throw new Error( config.strings.failed );
					const file = new File( [ await response.blob() ], size.filename, { type: size.mime } );
					// Animated WebP needs Cimo's animation converter, not a static canvas.
					if ( file.type === 'image/webp' && new TextDecoder( 'latin1' ).decode( await file.arrayBuffer() ).includes( 'ANIM' ) ) { counts.unchanged++; continue; }
					if ( await animatedPng( file ) ) { counts.unchanged++; continue; }
					const result = await optimizeSize( file );
					if ( result.reason === 'resulting-media-bigger-than-input' ) { counts.unchanged++; continue; }
					if ( result.error || result.reason || ! result.metadata || result.file.type !== size.mime ) throw new Error( result.reason || result.error || config.strings.failed );
					if ( result.file.size >= file.size ) { counts.unchanged++; continue; }
					const upload = new FormData();
					upload.append( 'file', result.file ); upload.append( 'size', size.size );
					for ( const key of [ 'originalFormat', 'originalFilesize', 'convertedFormat', 'convertedFilesize', 'conversionTime', 'compressionSavings' ] ) {
						if ( result.metadata[ key ] !== undefined ) upload.append( key, String( result.metadata[ key ] ) );
					}
					const saved = await json( `${ config.restUrl }attachments/${ id }/upload-size`, { method: 'POST', headers: { 'X-WP-Nonce': config.restNonce }, body: upload } );
					if ( ! saved.success ) throw new Error( config.strings.failed );
					counts.optimized++;
				} catch ( error ) { counts.failed++; errors.push( `${ size.filename }: ${ error.message || config.strings.failed }` ); }
			}
			return { ...counts, message: config.strings.complete.replace( '%1$d', counts.optimized ).replace( '%2$d', counts.unchanged ).replace( '%3$d', counts.failed ) + ( errors.length ? ' ' + errors[0] : '' ) };
		} finally { busy = false; }
	}
	window.addEventListener( 'beforeunload', event => { if ( busy ) { event.preventDefault(); event.returnValue = ''; } } );
	window.uplinkMbbCimo = { optimize, get busy() { return busy; }, strings: config.strings };
}() );
