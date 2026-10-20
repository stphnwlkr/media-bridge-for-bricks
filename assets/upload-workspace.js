( function ( $, wp ) {
	'use strict';
	const cimoBypassFrames = new WeakMap();
	const uploadWorkspaceFrames = new WeakMap();
	function formatBytes( bytes ) {
		const value = Number( bytes ) || 0;
		if ( value < 1024 ) return `${ value } B`;
		if ( value < 1024 * 1024 ) return `${ ( value / 1024 ).toFixed( 1 ) } KB`;
		if ( value < 1024 * 1024 * 1024 ) return `${ ( value / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
		return `${ ( value / ( 1024 * 1024 * 1024 ) ).toFixed( 1 ) } GB`;
	}

	function formatString( template, values ) {
		let output = template || '';
		values.forEach( ( value, index ) => {
			output = output.replace( `%${ index + 1 }$d`, value ).replace( `%${ index + 1 }$s`, value );
		} );
		return output.replace( '%d', values[ 0 ] ?? '' ).replace( '%s', values[ 0 ] ?? '' );
	}

	function restoreCimoOptimization( frame ) {
		const state = cimoBypassFrames.get( frame );
		if ( ! state?.active ) {
			return;
		}
		if ( window.cimoSettings ) {
			window.cimoSettings.disableOptimization = state.previous;
		}
		state.active = false;
	}

	function setCimoBypass( frame, enabled ) {
		if ( ! window.cimoSettings ) {
			return;
		}
		let state = cimoBypassFrames.get( frame );
		if ( ! state ) {
			state = { active: false, previous: false };
			cimoBypassFrames.set( frame, state );
		}
		if ( enabled && ! state.active ) {
			state.previous = Boolean( window.cimoSettings.disableOptimization );
			state.active = true;
			window.cimoSettings.disableOptimization = true;
		} else if ( ! enabled ) {
			restoreCimoOptimization( frame );
		}
	}

	function renderUploadWorkspace( frame, config, request ) {
		const $content = frame.$el?.find( '.media-frame-content' );
		const $uploader = $content?.find( '.uploader-inline' ).first();
		if ( ! $uploader?.length || $uploader.find( '.uplink-mbb-upload-workspace' ).length ) {
			return;
		}
		const cimoAvailable = Boolean( config.cimoAvailable && window.cimoSettings );

		restoreCimoOptimization( frame );
		const state = {
			files: [],
			selectedFile: null,
			fileNames: new WeakMap(),
			previewUrls: new Map(),
			keepOriginals: false,
			uploading: false,
			uploadModels: new Map(),
			batchFiles: [],
			successfulIds: new Set(),
			failures: new Map(),
			fileErrors: new Map(),
			batchSummary: '',
			batchFinished: false,
		};
		uploadWorkspaceFrames.set( frame, state );

		const inputId = `uplink-mbb-upload-input-${ Math.random().toString( 36 ).slice( 2 ) }`;
		const helpId = `uplink-mbb-upload-help-${ Math.random().toString( 36 ).slice( 2 ) }`;
		const $input = $( '<input class="uplink-mbb-upload-input" type="file" multiple />' ).attr( 'id', inputId ).prop( 'multiple', ! frame.uplinkMbbReplaceUpload );
		const $destination = $( '<select class="uplink-mbb-upload-destination" />' ).append(
			$( '<option />' ).val( 0 ).text( config.strings.uncategorized )
		);
		( config.uploadDestinations || [] ).forEach( ( destination ) => {
			$destination.append(
				$( '<option />' )
					.val( destination.id )
					.text( `${ '\u00a0'.repeat( Number( destination.depth ) * 3 ) }${ destination.name }` )
			);
		} );
		const initialDestination = Number( frame.uplinkMbbUploadDestinationId ?? frame.uplinkMbbCollectionId ) || 0;
		$destination.val( String( initialDestination ) );
		const $newCollection = $( '<button type="button" class="uplink-mbb-upload-new-collection" aria-expanded="false" />' ).text( config.strings.newCollection );
		const $collectionName = $( '<input type="text" class="uplink-mbb-upload-collection-name" />' ).attr( 'placeholder', config.strings.collectionName );
		const $collectionParent = $( '<select class="uplink-mbb-upload-collection-parent" />' ).append(
			$( '<option />' ).val( 0 ).text( config.strings.topLevel )
		);
		( config.uploadDestinations || [] ).forEach( ( destination ) => {
			if ( Number( destination.depth ) >= Number.MAX_SAFE_INTEGER - 1 ) {
				return;
			}
			$collectionParent.append(
				$( '<option />' )
					.val( destination.id )
					.text( `${ '\u00a0'.repeat( Number( destination.depth ) * 3 ) }${ destination.name }` )
			);
		} );
		$collectionParent.val( String( initialDestination ) );
		const $collectionStatus = $( '<p class="uplink-mbb-upload-collection-status" aria-live="polite" />' );
		const $collectionForm = $( '<form class="uplink-mbb-upload-collection-form" hidden />' ).append(
			$( '<label />' ).append( $( '<span />' ).text( config.strings.collectionName ), $collectionName ),
			$( '<label />' ).append( $( '<span />' ).text( config.strings.parentCollection ), $collectionParent ),
			$( '<div class="uplink-mbb-upload-collection-actions" />' ).append(
				$( '<button type="button" class="button uplink-mbb-upload-collection-cancel" />' ).text( config.strings.cancelUpload ),
				$( '<button type="submit" class="button button-primary" />' ).text( config.strings.createCollection )
			),
			$collectionStatus
		);

		const $list = $( '<div class="uplink-mbb-upload-list" />' );
		const $summary = $( '<p class="uplink-mbb-upload-summary" aria-live="polite" />' );
		const $clear = $( '<button type="button" class="uplink-mbb-upload-clear" />' ).text( config.strings.clearFiles );
		const $upload = $( '<button type="button" class="button button-primary uplink-mbb-upload-submit" disabled />' );
		const $progressText = $( '<span class="uplink-mbb-upload-progress-text" />' );
		const $progressBar = $( '<span class="uplink-mbb-upload-progress-value" />' );
		const $progress = $( '<div class="uplink-mbb-upload-progress" role="status" aria-live="polite" hidden />' ).append(
			$progressText,
			$( '<span class="uplink-mbb-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" />' ).append( $progressBar )
		);
		const $keep = $( '<input type="checkbox" />' );
		const $helpButton = $( '<button type="button" class="uplink-mbb-upload-help" aria-expanded="false" />' )
			.text( 'i' )
			.attr( { 'aria-controls': helpId, 'aria-describedby': helpId, 'aria-label': config.strings.keepOriginalsHelp } );
		const $helpPopover = $( '<div class="uplink-mbb-upload-help-popover" role="tooltip" hidden />' )
			.attr( 'id', helpId )
			.text( config.strings.keepOriginalsHelp );
		const $keepControl = $( '<div class="uplink-mbb-upload-keep" />' ).append(
			$( '<label />' ).append( $keep, $( '<span aria-hidden="true" class="uplink-mbb-upload-switch" />' ), $( '<span />' ).text( config.strings.keepOriginals ) ),
			$( '<span class="uplink-mbb-upload-help-wrap" />' ).append( $helpButton, $helpPopover )
		);
		const uploadIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5"/></svg>';
		const folderIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h6l2 2h10v9.5H3z"/></svg>';
		const fileIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6zM14 3v5h5M9 14l2-2 4 4M14.5 12.5l.01 0"/></svg>';
		const removeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M9 7V4h6v3m-8 0 1 14h8l1-14M10 11v6m4-6v6"/></svg>';

		const $dropzone = $( '<div class="uplink-mbb-upload-dropzone" />' ).append(
			$input,
			$( '<span class="uplink-mbb-upload-drop-icon" />' ).html( uploadIcon ),
			$( '<strong />' ).text( config.strings.dropFiles ),
			$( '<span class="uplink-mbb-upload-limits" />' ).text( `${ config.strings.acceptedFiles } · ${ config.maxUploadSize } ${ config.strings.perFile }` ),
			$( '<label class="button uplink-mbb-upload-choose" />' ).attr( 'for', inputId ).text( config.strings.chooseFiles )
		);
		const $workspace = $( '<section class="uplink-mbb-upload-workspace" />' ).append(
			$( '<header class="uplink-mbb-upload-header" />' ).append(
				$( '<div class="uplink-mbb-upload-header-content" />' ).append(
					$( '<h2 />' ).text( config.strings.uploadHeading ),
					$( '<p />' ).text( config.strings.uploadInstructions ),
					cimoAvailable ? $keepControl : null
				),
				$( '<span class="uplink-mbb-upload-max" />' ).text( `${ config.maxUploadSize } max` )
			),
			$( '<div class="uplink-mbb-upload-body" />' ).append(
				$( '<div class="uplink-mbb-upload-destination-heading" />' ).append(
					$( '<label />' ).attr( 'for', `${ inputId }-destination` ).text( config.strings.destination ),
					config.canManageCollections ? $newCollection : null
				),
				$( '<div class="uplink-mbb-upload-destination-control" />' ).append( $( '<span class="uplink-mbb-upload-folder-icon" />' ).html( folderIcon ), $destination.attr( 'id', `${ inputId }-destination` ) ),
				config.canManageCollections ? $collectionForm : null,
				$dropzone,
				$( '<div class="uplink-mbb-upload-queue-heading" />' ).append( $summary, $clear ),
				$list
			),
			$( '<footer class="uplink-mbb-upload-footer" />' ).append(
				$progress,
				$( '<div class="uplink-mbb-upload-actions" />' ).append(
					$( '<button type="button" class="button uplink-mbb-upload-cancel" />' ).text( config.strings.cancelUpload ),
					$upload
				)
			)
		);

		const $queueHeading = $workspace.find( '.uplink-mbb-upload-queue-heading' );
		const $footerControls = $( '<div class="uplink-mbb-upload-footer-controls" hidden />' );
		$workspace.find( '.uplink-mbb-upload-footer' ).prepend( $footerControls );

		function fileKey( file ) {
			return `${ file?.name || '' }\u0000${ Number( file?.size ) || 0 }`;
		}

		const canRename = () => ! frame.uplinkMbbCanRename || frame.uplinkMbbCanRename();
		frame.$el.on( 'change.uplinkMbbRename', 'input[name="mode"]', () => renderFiles() );
		function renderFiles() {
			$list.empty();
			if ( ! state.files.includes( state.selectedFile ) ) state.selectedFile = state.files[0] || null;
			const $queue = $( '<div class="uplink-mbb-upload-file-nav" role="group" />' ).attr( 'aria-label', config.strings.uploadHeading );
			const $editor = $( '<div class="uplink-mbb-upload-file-editor" />' );
			$list.toggleClass( 'has-queue', state.files.length > 1 );
			if ( state.files.length > 1 ) $list.append( $queue );
			$list.append( $editor );
			for ( const [ file, url ] of state.previewUrls ) {
				if ( ! state.files.includes( file ) ) { URL.revokeObjectURL( url ); state.previewUrls.delete( file ); }
			}
			let readyCount = 0;
			let readyBytes = 0;
			let invalidCount = 0;
			state.files.forEach( ( file, index ) => {
				const invalid = Number( file.size ) > Number( config.maxUploadBytes );
				const uploadError = state.fileErrors.get( fileKey( file ) ) || '';
				if ( invalid ) invalidCount += 1;
				else {
					readyCount += 1;
					readyBytes += Number( file.size ) || 0;
				}
				const status = invalid ? config.strings.fileOverLimit : ( uploadError || ( state.keepOriginals ? config.strings.fileKeptOriginal : config.strings.fileReady ) );
				const $item = $( '<div class="uplink-mbb-upload-file" />' ).prop( 'hidden', file !== state.selectedFile ).toggleClass( 'is-invalid', invalid ).toggleClass( 'is-error', Boolean( uploadError ) ).append(
					$( '<span class="uplink-mbb-upload-file-icon" />' ).html( fileIcon ),
					$( '<span class="uplink-mbb-upload-file-details" />' ).append( $( '<strong />' ).text( file.name ), $( '<span />' ).text( `${ formatBytes( file.size ) } · ${ status }` ) ),
					$( '<button type="button" class="uplink-mbb-upload-remove" />' ).attr( 'aria-label', formatString( config.strings.removeFile, [ file.name ] ) ).html( removeIcon )
				);
				if ( ! canRename() && frame.uplinkMbbKeptFilename?.filename ) {
					const kept = frame.uplinkMbbKeptFilename;
					$item.find( '.uplink-mbb-upload-file-details' ).append(
						$( '<span class="uplink-mbb-upload-kept-name" />' ).append(
							$( '<span />' ).text( kept.label + ' ' ),
							$( '<strong />' ).text( kept.filename ),
							$( '<small />' ).text( kept.help )
						)
					);
				}
				if ( file.type.startsWith( 'image/' ) ) {
					if ( ! state.previewUrls.has( file ) ) state.previewUrls.set( file, URL.createObjectURL( file ) );
					const $preview = $( '<img class="uplink-mbb-upload-preview" />' ).attr( { src: state.previewUrls.get( file ), alt: file.name } );
					$preview.on( 'error', () => { $preview.remove(); $item.removeClass( 'has-image-preview' ); } );
					$item.addClass( 'has-image-preview' ).prepend( $preview );
				}
				const $queueName = $( '<strong />' ).text( file.name );
				const $select = $( '<button type="button" class="uplink-mbb-upload-file-select" />' ).attr( 'aria-pressed', String( file === state.selectedFile ) ).append(
					state.previewUrls.has( file ) ? $( '<img alt="" />' ).attr( 'src', state.previewUrls.get( file ) ) : $( '<span />' ).html( fileIcon ),
					$( '<span />' ).append( $queueName, $( '<small />' ).text( `${ formatBytes( file.size ) } · ${ status }` ) )
				).on( 'click', () => {
					state.selectedFile = file;
					renderFiles();
					$list.find( '.uplink-mbb-upload-file-select' ).eq( index ).trigger( 'focus' );
				} );
				$queue.append( $select );
				const dot = file.name.lastIndexOf( '.' );
				const extension = dot > 0 ? file.name.slice( dot ) : '';
				const baseName = dot > 0 ? file.name.slice( 0, dot ) : file.name;
				const $name = $( '<input type="text" required maxlength="180" class="uplink-mbb-upload-filename" />' )
					.attr( 'aria-label', `${ config.strings.uploadFileName }: ${ file.name }` )
					.val( state.fileNames.get( file ) ?? baseName );
				const updateName = () => {
					state.fileNames.set( file, $name.val() );
					$queueName.text( `${ $name.val() }${ extension }` );
					$name[0].setCustomValidity( String( $name.val() ).trim() && ! /[\\/]/.test( $name.val() ) ? '' : config.strings.validFileName );
				};
				$name.on( 'input', updateName );
				updateName();
				if ( canRename() ) $item.find( '.uplink-mbb-upload-file-details' ).append(
					$( '<label class="uplink-mbb-upload-rename" />' ).append(
						$( '<span />' ).text( config.strings.uploadFileName ),
						$( '<span class="uplink-mbb-upload-rename-control" />' ).append( $name, $( '<span />' ).text( extension ) ),
						$( '<small />' ).text( config.strings.renameUploadHelp )
					)
				);
				$item.find( '.uplink-mbb-upload-remove' ).on( 'click', () => {
					state.fileErrors.delete( fileKey( file ) );
					state.files.splice( index, 1 );
					state.batchSummary = '';
					renderFiles();
				} );
				$editor.append( $item );
			} );

			if ( state.batchSummary ) {
				$summary.text( state.batchSummary );
			} else if ( state.files.length ) {
				$summary.text( ( frame.uplinkMbbReplaceUpload ? formatString( config.strings.fileSelected, [ formatBytes( readyBytes ) ] ) : formatString( config.strings.filesReady, [ readyCount, formatBytes( readyBytes ) ] ) ) + ( invalidCount ? ` · ${ formatString( config.strings.filesOverLimit, [ invalidCount ] ) }` : '' ) );
			} else {
				$summary.text( '' );
			}
			$clear.prop( 'hidden', ! state.files.length );
			$upload.prop( 'disabled', ! readyCount ).text( frame.uplinkMbbReplaceUpload ? config.strings.replaceFile : formatString( state.fileErrors.size ? config.strings.retryFiles : config.strings.uploadFiles, [ readyCount ] ) );
			$keep.prop( 'disabled', Boolean( state.files.length ) );
			$workspace.toggleClass( 'has-files', Boolean( state.files.length ) );
			$footerControls.prop( 'hidden', ! state.files.length );
			if ( state.files.length ) {
				$footerControls.append( $dropzone, $queueHeading );
			} else {
				$list.before( $dropzone, $queueHeading );
			}
		}

		function stageFiles( files ) {
			if ( state.uploading ) return;
			if ( frame.uplinkMbbReplaceUpload && files?.length > 1 ) {
				$input.val( '' );
				window.uplinkMbbToast( config.strings.chooseOneFile, 'error' );
				return;
			}
			if ( frame.uplinkMbbReplaceUpload ) state.files = [];
			Array.from( files || [] ).forEach( ( file ) => {
				const duplicate = state.files.some( ( queued ) => queued.name === file.name && queued.size === file.size && queued.lastModified === file.lastModified );
				if ( ! duplicate ) state.files.push( file );
			} );
			$input.val( '' );
			renderFiles();
		}

		$input.on( 'change', function () {
			stageFiles( this.files );
		} );
		$dropzone.on( 'dragenter dragover', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			$dropzone.addClass( 'is-dragging' );
		} ).on( 'dragleave', function ( event ) {
			event.stopPropagation();
			if ( ! this.contains( event.relatedTarget ) ) $dropzone.removeClass( 'is-dragging' );
		} ).on( 'drop', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			$dropzone.removeClass( 'is-dragging' );
			stageFiles( event.originalEvent?.dataTransfer?.files );
		} );
		$destination.on( 'change', function () {
			frame.uplinkMbbUploadDestinationId = Number( this.value ) || 0;
		} );
		$newCollection.on( 'click', function () {
			const open = $collectionForm.prop( 'hidden' );
			$collectionForm.prop( 'hidden', ! open );
			$newCollection.attr( 'aria-expanded', String( open ) );
			$collectionStatus.text( '' );
			if ( open ) {
				$collectionParent.val( $destination.val() );
				window.requestAnimationFrame( () => $collectionName.trigger( 'focus' ) );
			}
		} );
		$collectionForm.find( '.uplink-mbb-upload-collection-cancel' ).on( 'click', function () {
			$collectionForm.prop( 'hidden', true );
			$newCollection.attr( 'aria-expanded', 'false' ).trigger( 'focus' );
		} );
		$collectionForm.on( 'submit', async function ( event ) {
			event.preventDefault();
			const name = $collectionName.val().trim();
			if ( ! name ) {
				$collectionName.trigger( 'focus' );
				return;
			}
			const $submit = $collectionForm.find( '[type="submit"]' ).prop( 'disabled', true );
			$collectionStatus.text( '' );
			try {
				const parentId = Number( $collectionParent.val() ) || 0;
				const result = await request( 'uplink_mbb_save_collection', { term_id: 0, name, parent: parentId } );
				const parent = ( config.uploadDestinations || [] ).find( ( destination ) => Number( destination.id ) === parentId );
				const created = { id: Number( result.term_id ), name, depth: parent ? Number( parent.depth ) + 1 : 0 };
				config.uploadDestinations.push( created );
				const optionText = `${ '\u00a0'.repeat( created.depth * 3 ) }${ created.name }`;
				$destination.append( $( '<option />' ).val( created.id ).text( optionText ) ).val( String( created.id ) ).trigger( 'change' );
				if ( created.depth < Number.MAX_SAFE_INTEGER - 1 ) {
					$collectionParent.append( $( '<option />' ).val( created.id ).text( optionText ) );
				}
				$collectionName.val( '' );
				window.uplinkMbbToast( config.strings.collectionCreated );
				window.setTimeout( () => {
					$collectionForm.prop( 'hidden', true );
					$newCollection.attr( 'aria-expanded', 'false' ).trigger( 'focus' );
				}, 700 );
			} catch ( error ) {
				window.uplinkMbbToast( error.message, 'error' );
			} finally {
				$submit.prop( 'disabled', false );
			}
		} );
		$helpButton.on( 'click', function ( event ) {
			event.stopPropagation();
			const open = $helpPopover.prop( 'hidden' );
			$helpPopover.prop( 'hidden', ! open );
			$helpButton.attr( 'aria-expanded', String( open ) );
		} );
		$helpPopover.on( 'click', ( event ) => event.stopPropagation() );
		$( document ).off( 'click.uplinkMbbUploadHelp' ).on( 'click.uplinkMbbUploadHelp', function ( event ) {
			if ( ! $( event.target ).closest( '.uplink-mbb-upload-help-wrap' ).length ) {
				$helpPopover.prop( 'hidden', true );
				$helpButton.attr( 'aria-expanded', 'false' );
			}
		} );
		$helpButton.on( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				$helpPopover.prop( 'hidden', true );
				$helpButton.attr( 'aria-expanded', 'false' );
			}
		} );
		$keep.on( 'change', function () {
			state.keepOriginals = this.checked;
			setCimoBypass( frame, this.checked );
			renderFiles();
		} );
		$clear.on( 'click', () => {
			state.files = [];
			state.fileErrors.clear();
			state.batchSummary = '';
			renderFiles();
		} );
		$workspace.find( '.uplink-mbb-upload-cancel' ).on( 'click', () => frame.close() );

		function updateUploadProgress() {
			const total = Number( frame.uplinkMbbStagedUploadCount ) || state.uploadModels.size;
			const complete = state.successfulIds.size + state.failures.size;
			let progress = complete * 100;
			state.uploadModels.forEach( ( item ) => {
				const uploading = Boolean( item.attachment.get( 'uploading' ) );
				if ( uploading && ! state.failures.has( item.fileKey ) ) {
					progress += Number( item.attachment.get( 'percent' ) ) || 0;
				}
			} );
			const percent = total ? Math.min( 100, Math.round( progress / total ) ) : 0;
			$progress.prop( 'hidden', false );
			$progressText.text( formatString( config.strings.uploadingFiles, [ Math.min( complete, total ), total ] ) );
			$progress.find( '[role="progressbar"]' ).attr( 'aria-valuenow', percent );
			$progressBar.css( 'width', `${ percent }%` );
		}

		function restoreUploadControls() {
			state.uploading = false;
			$workspace.removeAttr( 'aria-busy' ).removeClass( 'is-uploading' );
			$workspace.find( 'input, select, button' ).prop( 'disabled', false );
			renderFiles();
		}

		async function finishUploadBatch() {
			const total = Number( frame.uplinkMbbStagedUploadCount ) || state.batchFiles.length;
			const settled = state.successfulIds.size + state.failures.size;
			if ( state.batchFinished || ! total || settled < total ) return;

			state.batchFinished = true;
			updateUploadProgress();
			if ( ! state.failures.size && 'function' === typeof frame.uplinkMbbFinishSuccessfulUploadBatch ) {
				await frame.uplinkMbbFinishSuccessfulUploadBatch( Array.from( state.successfulIds ) );
				return;
			}

			const failedKeys = new Set( state.failures.keys() );
			state.files = state.files.filter( ( file ) => Number( file.size ) > Number( config.maxUploadBytes ) || failedKeys.has( fileKey( file ) ) );
			state.batchSummary = state.failures.size
				? formatString( config.strings.uploadResult, [ state.successfulIds.size, state.failures.size ] )
				: formatString( config.strings.uploadComplete, [ state.successfulIds.size ] );
			$progressText.text( state.batchSummary );
			if ( state.successfulIds.size ) frame.uplinkMbbRefreshUploadResults?.();
			restoreUploadControls();
		}

		const recordUploadSuccess = async ( id ) => {
			if ( ! state.uploading ) return false;
			state.successfulIds.add( Number( id ) );
			updateUploadProgress();
			await finishUploadBatch();
			return true;
		};
		frame.uplinkMbbRecordUploadSuccess = recordUploadSuccess;

		const handleUploadError = ( errorModel ) => {
			if ( ! state.uploading ) return;
			const uploadFile = errorModel?.get?.( 'file' ) || errorModel?.file;
			const key = fileKey( uploadFile );
			const queuedFile = state.batchFiles.find( ( file ) => fileKey( file ) === key );
			if ( ! queuedFile || state.failures.has( key ) ) return;

			const message = errorModel?.get?.( 'message' ) || errorModel?.message || config.strings.uploadFailed;
			state.failures.set( key, { file: queuedFile, message } );
			state.fileErrors.set( key, message );
			updateUploadProgress();
			finishUploadBatch().catch( () => restoreUploadControls() );
		};
		wp.Uploader?.errors?.on?.( 'add', handleUploadError );

		frame.uplinkMbbTrackUpload = ( attachment ) => {
			if ( ! state.uploading || state.uploadModels.has( attachment.cid ) ) return;
			const update = () => updateUploadProgress();
			const attachmentFile = attachment.get( 'file' );
			state.uploadModels.set( attachment.cid, { attachment, update, fileKey: fileKey( attachmentFile || { name: attachment.get( 'filename' ), size: attachment.get( 'size' ) } ) } );
			attachment.on( 'change:percent change:uploading change:id', update );
			updateUploadProgress();
		};

		frame.once( 'close', () => {
			frame.$el.off( 'change.uplinkMbbRename' );
			state.previewUrls.forEach( url => URL.revokeObjectURL( url ) );
			state.previewUrls.clear();
			restoreCimoOptimization( frame );
			state.uploadModels.forEach( ( item ) => item.attachment.off( null, item.update ) );
			wp.Uploader?.errors?.off?.( 'add', handleUploadError );
			delete frame.uplinkMbbTrackUpload;
			if ( frame.uplinkMbbRecordUploadSuccess === recordUploadSuccess ) delete frame.uplinkMbbRecordUploadSuccess;
		} );

		$upload.on( 'click', async function () {
			const invalidIndex = $list.find( '.uplink-mbb-upload-filename' ).toArray().findIndex( input => ! input.validity.valid );
			if ( invalidIndex >= 0 ) {
				state.selectedFile = state.files[ invalidIndex ];
				renderFiles();
				$list.find( '.uplink-mbb-upload-file:not([hidden]) .uplink-mbb-upload-filename' )[0].reportValidity();
				return;
			}
			state.files = state.files.map( ( file ) => {
				if ( ! canRename() ) return file;
				const draft = state.fileNames.get( file );
				if ( draft === undefined ) return file;
				const dot = file.name.lastIndexOf( '.' );
				const extension = dot > 0 ? file.name.slice( dot ) : '';
				return new File( [ file ], `${ draft.trim() }${ extension }`, { type: file.type, lastModified: file.lastModified } );
			} );
			const files = state.files.filter( ( file ) => Number( file.size ) <= Number( config.maxUploadBytes ) );
			if ( frame.uplinkMbbReplaceUpload ) {
				if ( ! files.length || state.uploading ) return;
				state.uploading = true;
				$workspace.attr( 'aria-busy', 'true' );
				$workspace.find( 'input, select, button' ).prop( 'disabled', true );
				try { await frame.uplinkMbbReplaceUpload( files[0] ); }
				catch ( error ) { window.uplinkMbbToast( error.message, 'error' ); }
				finally { restoreUploadControls(); }
				return;
			}
			const uploader = frame.uploader?.uploader?.uploader;
			if ( ! files.length || ! uploader?.addFile ) return;
			state.uploading = true;
			state.batchFiles = files.slice();
			state.successfulIds.clear();
			state.failures.clear();
			state.fileErrors.clear();
			state.batchSummary = '';
			state.batchFinished = false;
			state.uploadModels.forEach( ( item ) => item.attachment.off( null, item.update ) );
			state.uploadModels.clear();
			frame.uplinkMbbUploadDestinationId = Number( $destination.val() ) || 0;
			frame.uplinkMbbStagedUploadCount = files.length;
			frame.uplinkMbbCompletedUploadIds = [];
			$workspace.attr( 'aria-busy', 'true' ).addClass( 'is-uploading' );
			$workspace.find( 'input, select, button' ).prop( 'disabled', true );
			$upload.text( config.strings.uploading );
			$progress.prop( 'hidden', false );
			$progressText.text( formatString( config.strings.uploadingFiles, [ 0, files.length ] ) );
			uploader.addFile( files );
		} );

		$uploader.children().attr( 'hidden', true );
		$uploader.append( $workspace );
		if ( frame.uplinkMbbReplaceUpload ) {
			$workspace.find( '.uplink-mbb-upload-destination-heading, .uplink-mbb-upload-destination-control, .uplink-mbb-upload-collection-form' ).remove();
			$workspace.find( '.uplink-mbb-upload-choose' ).text( config.strings.chooseFile );
			$workspace.find( '.uplink-mbb-upload-dropzone > strong' ).text( config.strings.dropFile );
			$workspace.find( '.uplink-mbb-upload-header h2' ).text( config.strings.replacementHeading );
			$workspace.find( '.uplink-mbb-upload-header-content > p' ).text( config.strings.replacementInstructions );
		}

		renderFiles();
		window.requestAnimationFrame( () => frame.uploader?.refresh?.() );
	}

	window.uplinkMbbUploadWorkspace = { render: renderUploadWorkspace, restore: restoreCimoOptimization };
}( jQuery, window.wp ) );
