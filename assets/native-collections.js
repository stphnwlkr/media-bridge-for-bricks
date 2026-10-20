( function ( $, wp ) {
	'use strict';

	const config = window.uplinkMbbNativeCollections;
	if ( ! config || ! wp?.media ) {
		return;
	}

	let uploadCollection = 0;
	const uploaders = new Set();
	const childrenByParent = new Map();
	const collapsedCollections = new Set();
	let collectionOrderSaving = false;
	let collectionPointerDrag = null;
	let suppressCollectionClick = false;
	const panelWidthStorageKey = 'uplink-mbb-collection-panel-width';
	const panelWidthDefault = 280;
	const panelWidthMin = 220;
	const panelWidthMax = 480;
	const fontExtensions = new Set( [ 'woff', 'woff2', 'ttf', 'otf' ] );
	const fontFacePromises = new Map();
	const fontPreviewData = new WeakMap();
	const fontPreviewObserver = 'IntersectionObserver' in window ? new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			if ( ! entry.isIntersecting ) {
				return;
			}
			fontPreviewObserver.unobserve( entry.target );
			loadFontPreview( entry.target, fontPreviewData.get( entry.target ) );
		} );
	}, { rootMargin: '160px' } ) : null;

	function fontExtension( attachment ) {
		return ( attachment?.filename || attachment?.url || '' ).split( '.' ).pop().split( /[?#]/ )[ 0 ].toLowerCase();
	}

	function isFontAttachment( attachment ) {
		return Boolean( attachment?.url && ( ( attachment.mime || '' ).startsWith( 'font/' ) || fontExtensions.has( fontExtension( attachment ) ) ) );
	}

	async function loadFontPreview( preview, attachment ) {
		if ( ! preview?.isConnected || ! isFontAttachment( attachment ) || 'function' !== typeof window.FontFace ) {
			return;
		}
		const family = `uplink-mbb-native-font-${ attachment.id }`;
		let promise = fontFacePromises.get( attachment.id );
		if ( ! promise ) {
			const face = new FontFace( family, `url(${ JSON.stringify( attachment.url ) })` );
			promise = face.load().then( ( loadedFace ) => {
				document.fonts.add( loadedFace );
				return family;
			} );
			fontFacePromises.set( attachment.id, promise );
		}
		try {
			preview.style.fontFamily = await promise;
			preview.classList.add( 'is-loaded' );
		} catch ( error ) {
			fontFacePromises.delete( attachment.id );
			preview.classList.add( 'is-failed' );
		}
	}

	function enableFontPreviews( view ) {
		if ( view.uplinkMbbFontPreviewObserver ) {
			return;
		}
		const enhance = () => {
			view.el.querySelectorAll( '.attachment[data-id]' ).forEach( ( tile ) => {
				if ( tile.dataset.uplinkMbbFontPreview ) {
					return;
				}
				const attachment = wp.media.model.Attachment.get( Number( tile.dataset.id ) )?.toJSON();
				if ( ! isFontAttachment( attachment ) ) {
					return;
				}
				const thumbnail = tile.querySelector( '.thumbnail' );
				if ( ! thumbnail ) {
					return;
				}
				tile.dataset.uplinkMbbFontPreview = '1';
				const preview = document.createElement( 'span' );
				const sample = document.createElement( 'span' );
				const extension = document.createElement( 'span' );
				preview.className = 'uplink-mbb-native-font-preview';
				sample.className = 'uplink-mbb-native-font-sample';
				sample.textContent = 'Aa';
				extension.className = 'uplink-mbb-native-font-extension';
				extension.textContent = fontExtension( attachment );
				preview.append( sample, extension );
				thumbnail.append( preview );
				if ( fontPreviewObserver ) {
					fontPreviewData.set( preview, attachment );
					fontPreviewObserver.observe( preview );
				} else {
					loadFontPreview( preview, attachment );
				}
			} );
		};
		enhance();
		view.uplinkMbbFontPreviewObserver = new MutationObserver( enhance );
		view.uplinkMbbFontPreviewObserver.observe( view.el, { childList: true, subtree: true } );
	}

	function clampPanelWidth( width ) {
		return Math.min( panelWidthMax, Math.max( panelWidthMin, Math.round( Number( width ) || panelWidthDefault ) ) );
	}

	function storedPanelWidth() {
		try {
			const savedWidth = window.localStorage.getItem( panelWidthStorageKey );
			return null === savedWidth || '' === savedWidth ? panelWidthDefault : clampPanelWidth( savedWidth );
		} catch ( error ) {
			return panelWidthDefault;
		}
	}

	function savePanelWidth( width ) {
		try {
			window.localStorage.setItem( panelWidthStorageKey, String( clampPanelWidth( width ) ) );
		} catch ( error ) {
			// Resizing still works when browser storage is unavailable.
		}
	}

	function enablePanelResize( view, layoutHost, handle ) {
		let width = storedPanelWidth();
		let resizing = false;
		let pointerId = null;
		const applyWidth = ( nextWidth, persist = false ) => {
			width = clampPanelWidth( nextWidth );
			layoutHost.style.setProperty( '--uplink-mbb-native-panel-open-width', `${ width }px` );
			handle.setAttribute( 'aria-valuenow', String( width ) );
			if ( persist ) {
				savePanelWidth( width );
			}
		};
		const widthFromPointer = ( event ) => {
			const bounds = layoutHost.getBoundingClientRect();
			return 'rtl' === window.getComputedStyle( layoutHost ).direction ? bounds.right - event.clientX : event.clientX - bounds.left;
		};
		const finish = ( event ) => {
			if ( ! resizing || event.pointerId !== pointerId ) {
				return;
			}
			resizing = false;
			handle.classList.remove( 'is-active' );
			document.body.classList.remove( 'uplink-mbb-resizing-collections' );
			if ( handle.hasPointerCapture?.( pointerId ) ) {
				handle.releasePointerCapture( pointerId );
			}
			pointerId = null;
			applyWidth( width, true );
			view.controller?.uploader?.refresh?.();
		};

		applyWidth( width );
		handle.addEventListener( 'pointerdown', ( event ) => {
			if ( 0 !== event.button || layoutHost.classList.contains( 'uplink-mbb-native-collections-collapsed' ) ) {
				return;
			}
			resizing = true;
			pointerId = event.pointerId;
			handle.setPointerCapture?.( pointerId );
			handle.classList.add( 'is-active' );
			document.body.classList.add( 'uplink-mbb-resizing-collections' );
			event.preventDefault();
		} );
		handle.addEventListener( 'pointermove', ( event ) => {
			if ( resizing && event.pointerId === pointerId ) {
				applyWidth( widthFromPointer( event ) );
			}
		} );
		handle.addEventListener( 'pointerup', finish );
		handle.addEventListener( 'pointercancel', finish );
		handle.addEventListener( 'dblclick', () => applyWidth( panelWidthDefault, true ) );
		handle.addEventListener( 'keydown', ( event ) => {
			const direction = 'rtl' === window.getComputedStyle( layoutHost ).direction ? -1 : 1;
			let nextWidth = width;
			if ( 'ArrowLeft' === event.key ) {
				nextWidth -= 10 * direction;
			} else if ( 'ArrowRight' === event.key ) {
				nextWidth += 10 * direction;
			} else if ( 'Home' === event.key ) {
				nextWidth = panelWidthMin;
			} else if ( 'End' === event.key ) {
				nextWidth = panelWidthMax;
			} else {
				return;
			}
			event.preventDefault();
			applyWidth( nextWidth, true );
			view.controller?.uploader?.refresh?.();
		} );
	}

	function keepStandalonePanelMounted( layoutHost, view ) {
		layoutHost.uplinkMbbNativeCollectionsView = view;
		if ( layoutHost.uplinkMbbNativeCollectionsObserver || ! window.MutationObserver ) {
			return;
		}
		const observer = new MutationObserver( () => {
			const activeView = layoutHost.uplinkMbbNativeCollectionsView;
			if ( ! layoutHost.isConnected || ! activeView?.el?.isConnected || layoutHost.querySelector( ':scope > .uplink-mbb-native-collections-panel' ) ) {
				return;
			}
			window.requestAnimationFrame( () => {
				if ( layoutHost.isConnected && activeView.el?.isConnected && ! layoutHost.querySelector( ':scope > .uplink-mbb-native-collections-panel' ) ) {
					createPanel( activeView );
				}
			} );
		} );
		observer.observe( layoutHost, { childList: true } );
		layoutHost.uplinkMbbNativeCollectionsObserver = observer;
	}

	function keepStandalonePanelInViewport( layoutHost, panel ) {
		layoutHost.uplinkMbbNativeViewportPanel = panel;
		const syncPosition = () => {
			const activePanel = layoutHost.uplinkMbbNativeViewportPanel;
			if ( ! layoutHost.isConnected || ! activePanel?.isConnected ) {
				return;
			}
			const bounds = layoutHost.getBoundingClientRect();
			const adminBarBottom = document.querySelector( '#wpadminbar' )?.getBoundingClientRect().bottom || 0;
			const inlineStart = 'rtl' === window.getComputedStyle( layoutHost ).direction ? window.innerWidth - bounds.right : bounds.left;
			layoutHost.style.setProperty( '--uplink-mbb-native-panel-viewport-top', `${ Math.max( 0, Math.round( adminBarBottom ) ) }px` );
			layoutHost.style.setProperty( '--uplink-mbb-native-panel-viewport-inline-start', `${ Math.max( 0, Math.round( inlineStart ) ) }px` );
		};
		if ( layoutHost.uplinkMbbNativeViewportSync ) {
			layoutHost.uplinkMbbNativeViewportSync();
			return;
		}
		let pending = false;
		const scheduleSync = () => {
			if ( pending ) {
				return;
			}
			pending = true;
			window.requestAnimationFrame( () => {
				pending = false;
				syncPosition();
			} );
		};
		layoutHost.uplinkMbbNativeViewportSync = scheduleSync;
		window.addEventListener( 'resize', scheduleSync );
		window.addEventListener( 'scroll', scheduleSync, { passive: true } );
		if ( window.ResizeObserver ) {
			const observer = new ResizeObserver( scheduleSync );
			observer.observe( layoutHost );
			layoutHost.uplinkMbbNativeViewportObserver = observer;
		}
		syncPosition();
	}

	function setCollectionData( collections ) {
		config.collections = Array.isArray( collections ) ? collections : [];
		childrenByParent.clear();
		config.collections.forEach( ( collection ) => {
			const parent = Number( collection.parent ) || 0;
			if ( ! childrenByParent.has( parent ) ) {
				childrenByParent.set( parent, [] );
			}
			childrenByParent.get( parent ).push( collection );
		} );
	}

	setCollectionData( config.collections );

	async function request( action, data = {} ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.entries( data ).forEach( ( [ key, value ] ) => body.append( key, value ) );
		const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) {
			throw new Error( payload.data?.message || config.strings.error );
		}
		return payload.data || {};
	}

	function folderIcon( iconName = 'category' ) {
		const icon = document.createElement( 'span' );
		icon.className = `dashicons dashicons-${ iconName }`;
		icon.setAttribute( 'aria-hidden', 'true' );
		return icon;
	}

	function countBadge( value ) {
		const count = document.createElement( 'span' );
		count.className = 'uplink-mbb-native-collection-count';
		count.textContent = null === value || undefined === value || '' === String( value ) ? '0' : String( value );
		return count;
	}

	function collectionCount( collection, hasChildren = false ) {
		const direct = Number( collection.count ) || 0;
		const total = Number.isFinite( Number( collection.totalCount ) ) ? Number( collection.totalCount ) : direct;
		if ( ! hasChildren || 'direct' === config.parentCountDisplay ) {
			return direct;
		}
		if ( 'direct_total' === config.parentCountDisplay ) {
			return `${ direct }/${ total }`;
		}
		return total;
	}

	function makeFilterButton( label, count, filter, collectionId = 0 ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'uplink-mbb-native-collection-filter';
		button.dataset.filter = filter;
		button.dataset.collection = String( collectionId );
		const iconName = 'all' === filter ? 'format-gallery' : 'category';
		const name = document.createElement( 'span' );
		name.className = 'uplink-mbb-native-collection-name';
		name.textContent = label;
		button.append( folderIcon( iconName ), name, countBadge( count ) );
		return button;
	}

	function siblingCollections( parent ) {
		return childrenByParent.get( Number( parent ) || 0 ) || [];
	}

	function clearCollectionDropIndicators( panel ) {
		panel.querySelectorAll( '.is-reorder-before, .is-reorder-after, .is-reordering' ).forEach( ( item ) => {
			item.classList.remove( 'is-reorder-before', 'is-reorder-after', 'is-reordering' );
		} );
	}

	async function saveCollectionOrder( view, panel, parent, orderedIds, focusId ) {
		if ( collectionOrderSaving ) {
			return;
		}
		collectionOrderSaving = true;
		try {
			await request( 'uplink_mbb_reorder_collections', {
				parent,
				ordered_ids: JSON.stringify( orderedIds ),
			} );
			await refreshCollectionState( view, panel );
			showPanelStatus( panel, config.strings.orderSaved );
			window.setTimeout( () => panel.querySelector( `[data-reorder-id="${ Number( focusId ) }"]` )?.focus(), 0 );
		} catch ( error ) {
			showPanelStatus( panel, error.message, 'error' );
		} finally {
			collectionOrderSaving = false;
			clearCollectionDropIndicators( panel );
		}
	}

	function moveCollectionByKeyboard( view, panel, collection, direction ) {
		const ids = siblingCollections( collection.parent ).map( ( sibling ) => Number( sibling.id ) );
		const current = ids.indexOf( Number( collection.id ) );
		const target = current + direction;
		if ( current < 0 || target < 0 || target >= ids.length ) {
			return;
		}
		[ ids[ current ], ids[ target ] ] = [ ids[ target ], ids[ current ] ];
		saveCollectionOrder( view, panel, collection.parent, ids, collection.id );
	}

	function enableCollectionPointerReorder( view, panel, row, handle, collection ) {
		handle.addEventListener( 'pointerdown', ( event ) => {
			if ( 0 !== event.button || collectionOrderSaving ) {
				return;
			}
			collectionPointerDrag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				sourceRow: row,
				target: null,
				placeAfter: false,
				moved: false,
			};
			handle.setPointerCapture?.( event.pointerId );
		} );
		handle.addEventListener( 'pointermove', ( event ) => {
			const drag = collectionPointerDrag;
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}
			if ( ! drag.moved && Math.hypot( event.clientX - drag.startX, event.clientY - drag.startY ) < 6 ) {
				return;
			}
			drag.moved = true;
			clearCollectionDropIndicators( panel );
			drag.sourceRow.classList.add( 'is-reordering' );
			const targetRow = document.elementFromPoint( event.clientX, event.clientY )?.closest( '.uplink-mbb-native-collection-row' );
			if ( ! targetRow || Number( targetRow.dataset.parent ) !== Number( collection.parent ) || Number( targetRow.dataset.collectionId ) === Number( collection.id ) ) {
				drag.target = null;
				return;
			}
			drag.target = targetRow;
			drag.placeAfter = event.clientY > targetRow.getBoundingClientRect().top + targetRow.offsetHeight / 2;
			targetRow.classList.add( drag.placeAfter ? 'is-reorder-after' : 'is-reorder-before' );
			event.preventDefault();
		} );
		const finishPointerReorder = ( event ) => {
			const drag = collectionPointerDrag;
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}
			collectionPointerDrag = null;
			if ( handle.hasPointerCapture?.( event.pointerId ) ) {
				handle.releasePointerCapture( event.pointerId );
			}
			if ( drag.moved ) {
				suppressCollectionClick = true;
				window.setTimeout( () => {
					suppressCollectionClick = false;
				}, 0 );
			}
			if ( drag.moved && drag.target ) {
				const ids = siblingCollections( collection.parent ).map( ( sibling ) => Number( sibling.id ) ).filter( ( id ) => id !== Number( collection.id ) );
				let targetIndex = ids.indexOf( Number( drag.target.dataset.collectionId ) );
				if ( drag.placeAfter ) {
					targetIndex += 1;
				}
				ids.splice( Math.max( 0, targetIndex ), 0, Number( collection.id ) );
				saveCollectionOrder( view, panel, collection.parent, ids, collection.id );
			} else {
				clearCollectionDropIndicators( panel );
			}
		};
		handle.addEventListener( 'pointerup', finishPointerReorder );
		handle.addEventListener( 'pointercancel', finishPointerReorder );
	}

	function collectionActionButton( action, label, iconName, collection ) {
		const button = iconButton( 'uplink-mbb-native-collection-action', `${ label }: ${ collection.name }`, iconName );
		button.dataset.collectionAction = action;
		button.dataset.collection = String( collection.id );
		return button;
	}

	function collectionBranch( view, panel, parent = 0 ) {
		const list = document.createElement( 'ul' );
		list.className = 'uplink-mbb-native-collection-tree';
		( childrenByParent.get( parent ) || [] ).forEach( ( collection ) => {
			const item = document.createElement( 'li' );
			const row = document.createElement( 'div' );
			row.className = 'uplink-mbb-native-collection-row';
			row.dataset.collectionId = String( collection.id );
			row.dataset.parent = String( collection.parent );
			const children = childrenByParent.get( Number( collection.id ) ) || [];
			if ( children.length ) {
				row.classList.add( 'has-children' );
				const toggle = document.createElement( 'button' );
				toggle.type = 'button';
				toggle.className = 'uplink-mbb-native-branch-toggle';
				toggle.setAttribute( 'aria-expanded', 'true' );
				toggle.setAttribute( 'aria-label', `${ config.strings.collapse }: ${ collection.name }` );
				toggle.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
				row.append( toggle );
			}
			const filter = makeFilterButton( collection.name, collectionCount( collection, Boolean( children.length ) ), 'collection', Number( collection.id ) );
			if ( config.canManage ) {
				row.classList.add( 'can-reorder' );
				filter.dataset.reorderId = String( collection.id );
				filter.setAttribute( 'aria-keyshortcuts', 'Alt+ArrowUp Alt+ArrowDown' );
				filter.setAttribute( 'aria-description', config.strings.reorderInstructions );
				filter.addEventListener( 'keydown', ( event ) => {
					if ( ! event.altKey || ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) ) {
						return;
					}
					event.preventDefault();
					moveCollectionByKeyboard( view, panel, collection, 'ArrowUp' === event.key ? -1 : 1 );
				} );
				enableCollectionPointerReorder( view, panel, row, filter, collection );
			}
			row.append( filter );
			if ( config.canManage ) {
				const actions = document.createElement( 'div' );
				actions.className = 'uplink-mbb-native-collection-actions';
				if ( config.canManage ) {
					actions.append( collectionActionButton( 'add-child', config.strings.addChild, 'plus-alt2', collection ) );
				}
				actions.append(
					collectionActionButton( 'edit', config.strings.editCollection, 'edit', collection ),
					collectionActionButton( 'delete', config.strings.deleteCollection, 'trash', collection )
				);
				row.append( actions );
			}
			item.append( row );
			if ( children.length ) {
				const childList = collectionBranch( view, panel, Number( collection.id ) );
				toggleBranch( item, row.querySelector( '.uplink-mbb-native-branch-toggle' ), childList, ! collapsedCollections.has( Number( collection.id ) ) );
				item.append( childList );
			}
			list.append( item );
		} );
		return list;
	}

	function toggleBranch( item, toggle, childList, expanded ) {
		item.classList.toggle( 'is-expanded', expanded );
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		toggle.setAttribute( 'aria-label', `${ expanded ? config.strings.collapse : config.strings.expand }: ${ item.querySelector( '.uplink-mbb-native-collection-filter' )?.textContent || '' }` );
		childList.hidden = ! expanded;
	}

	function parentCollectionIds() {
		return config.collections
			.filter( ( collection ) => ( childrenByParent.get( Number( collection.id ) ) || [] ).length )
			.map( ( collection ) => Number( collection.id ) );
	}

	function updateTreeToggle( panel ) {
		const button = panel.querySelector( '.uplink-mbb-native-tree-toggle' );
		if ( ! button ) {
			return;
		}
		const parentIds = parentCollectionIds();
		const allCollapsed = parentIds.length > 0 && parentIds.every( ( id ) => collapsedCollections.has( id ) );
		const label = allCollapsed ? config.strings.expandAllCollections : config.strings.collapseAllCollections;
		button.hidden = ! parentIds.length;
		button.dataset.treeAction = allCollapsed ? 'expand' : 'collapse';
		button.setAttribute( 'aria-label', label );
		button.title = label;
		button.querySelector( 'span' ).className = `uplink-mbb-tree-icon ${ allCollapsed ? 'is-open' : 'is-close' }`;
	}

	function refreshActiveFilters( panel, filter, collectionId ) {
		panel.querySelectorAll( '.uplink-mbb-native-collection-filter' ).forEach( ( button ) => {
			const active = button.dataset.filter === filter && Number( button.dataset.collection ) === collectionId;
			button.classList.toggle( 'is-active', active );
			if ( active ) {
				button.setAttribute( 'aria-current', 'true' );
			} else {
				button.removeAttribute( 'aria-current' );
			}
			if ( 'collection' === button.dataset.filter ) {
				const icon = button.querySelector( '.dashicons' );
				if ( icon ) {
					icon.className = `dashicons dashicons-${ active ? 'open-folder' : 'category' }`;
				}
			}
		} );
	}

	function applyFilter( view, filter, collectionId ) {
		if ( view.uplinkMbbNativeListMode ) {
			const url = new URL( window.location.href );
			url.searchParams.delete( 'uplink_mbb_collection' );
			url.searchParams.delete( 'uplink_mbb_uncategorized' );
			url.searchParams.delete( 'paged' );
			if ( 'collection' === filter ) {
				url.searchParams.set( 'uplink_mbb_collection', String( collectionId ) );
			} else if ( 'uncategorized' === filter ) {
				url.searchParams.set( 'uplink_mbb_uncategorized', '1' );
			}
			window.location.assign( url.toString() );
			return;
		}
		const props = view?.collection?.props;
		if ( ! props ) {
			return;
		}
		props.set( {
			uplink_mbb_collection: 'collection' === filter ? collectionId : null,
			uplink_mbb_uncategorized: 'uncategorized' === filter ? 1 : null,
		} );
		view.uplinkMbbNativeCollectionsFilter = { filter, collectionId };
		const panel = view.uplinkMbbNativeCollectionsPanel || view.el;
		refreshActiveFilters( panel, filter, collectionId );
	}

	function iconButton( className, label, iconName ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = `uplink-mbb-native-panel-action ${ className }`;
		button.setAttribute( 'aria-label', label );
		button.title = label;
		button.innerHTML = `<span class="dashicons dashicons-${ iconName }" aria-hidden="true"></span>`;
		return button;
	}

	function collectionDescendantIds( collectionId ) {
		const descendants = new Set();
		const visit = ( parent ) => {
			( childrenByParent.get( Number( parent ) ) || [] ).forEach( ( child ) => {
				const childId = Number( child.id );
				if ( descendants.has( childId ) ) {
					return;
				}
				descendants.add( childId );
				visit( childId );
			} );
		};
		visit( Number( collectionId ) );
		return descendants;
	}


	function fillCollectionSelect( select, includeUncategorized = false, parentMode = false, editedCollection = null ) {
		const selected = select.value;
		const excluded = editedCollection ? collectionDescendantIds( editedCollection.id ) : new Set();
		if ( editedCollection ) {
			excluded.add( Number( editedCollection.id ) );
		}
		select.replaceChildren();
		const first = document.createElement( 'option' );
		first.value = '0';
		first.textContent = includeUncategorized ? config.strings.uncategorized : config.strings.topLevel;
		select.append( first );
		config.collections.forEach( ( collection ) => {
			if ( parentMode && excluded.has( Number( collection.id ) ) ) {
				return;
			}
			const option = document.createElement( 'option' );
			option.value = String( collection.id );
			option.textContent = `${ '\u00a0\u00a0'.repeat( Number( collection.depth ) || 0 ) }${ collection.name }`;
			select.append( option );
		} );
		select.value = Array.from( select.options ).some( ( option ) => option.value === selected ) ? selected : '0';
	}

	function draggedAttachmentIds( view, attachment ) {
		if ( view.uplinkMbbNativeListMode ) {
			const selected = Array.from( view.el.querySelectorAll( '#the-list tr[id^="post-"]' ) ).filter( ( row ) => row.querySelector( 'input[type="checkbox"]:checked' ) );
			const sourceSelected = Boolean( attachment.querySelector( 'input[type="checkbox"]:checked' ) );
			const sources = sourceSelected && selected.length ? selected : [ attachment ];
			return sources.map( ( item ) => Number( item.dataset.id ) ).filter( Boolean );
		}
		const selected = Array.from( view.el.querySelectorAll( '.attachment.selected[data-id]' ) );
		const sources = attachment.classList.contains( 'selected' ) && selected.length ? selected : [ attachment ];
		return sources.map( ( item ) => Number( item.dataset.id ) ).filter( Boolean );
	}

	function attachmentFromTarget( view, target ) {
		return target.closest( view.uplinkMbbNativeListMode ? '.uplink-mbb-native-list-attachment[data-id]' : '.attachment[data-id]' );
	}

	function dragAssignmentHelper( view, attachment ) {
		const count = draggedAttachmentIds( view, attachment ).length;
		const helper = document.createElement( 'div' );
		helper.className = 'uplink-mbb-native-drag-helper';
		const title = document.createElement( 'strong' );
		title.textContent = 1 === count
			? config.strings.dragAssignOne
			: config.strings.dragAssignMany.replace( '%d', String( count ) );
		const instructions = document.createElement( 'span' );
		instructions.textContent = config.strings.dragAssignHelp;
		helper.append( title, instructions );
		return helper;
	}

	async function assignDroppedMedia( view, panel, target, ids ) {
		if ( view.uplinkMbbNativeAssignmentBusy ) {
			return;
		}
		view.uplinkMbbNativeAssignmentBusy = true;
		const clear = 'uncategorized' === target.dataset.filter;
		try {
			await request( 'uplink_mbb_assign_media', {
				media_ids: JSON.stringify( ids ),
				collection: clear ? 0 : Number( target.dataset.collection ),
				mode: clear ? 'clear' : 'add',
			} );
			await refreshCollectionState( view, panel );
			if ( view.uplinkMbbNativeListMode ) {
				if ( clear && 'all' !== view.uplinkMbbNativeCollectionsFilter?.filter ) {
					window.location.reload();
					return;
				}
			} else {
				view.collection.props.set( 'uplink_mbb_refresh', Date.now() );
			}
			showPanelStatus( panel, ( clear ? config.strings.cleared : config.strings.assigned ).replace( '%d', String( ids.length ) ) );
		} catch ( error ) {
			showPanelStatus( panel, error.message, 'error' );
		} finally {
			view.uplinkMbbNativeAssignmentBusy = false;
		}
	}

	function enableJqueryDropTargets( view, panel ) {
		if ( ! view.uplinkMbbNativeDragAssignment || ! $.fn.droppable ) {
			return;
		}
		panel.querySelectorAll( '.uplink-mbb-native-collection-filter:not([data-filter="all"])' ).forEach( ( target ) => {
			$( target ).droppable( {
				accept: '.attachment[data-id], .uplink-mbb-native-list-attachment[data-id]',
				scope: 'uplink-mbb-native-collections',
				tolerance: 'pointer',
				over: () => target.classList.add( 'is-drop-target' ),
				out: () => target.classList.remove( 'is-drop-target' ),
				drop: ( event, ui ) => {
					target.classList.remove( 'is-drop-target' );
					const attachment = ui.draggable?.[ 0 ];
					const ids = attachment ? draggedAttachmentIds( view, attachment ) : [];
					if ( ids.length ) {
						assignDroppedMedia( view, panel, target, ids );
					}
				},
			} );
		} );
	}

	function renderPanelBody( panel, view ) {
		const body = panel.querySelector( '.uplink-mbb-native-collections-body' );
		const contents = [
			makeFilterButton( config.strings.allMedia, config.counts.all, 'all' ),
			makeFilterButton( config.strings.uncategorized, config.counts.uncategorized, 'uncategorized' ),
			collectionBranch( view, panel )
		];
		if ( view.uplinkMbbNativeDragAssignment ) {
			const help = document.createElement( 'p' );
			help.className = 'uplink-mbb-native-assignment-help';
			help.textContent = config.strings.assignHelp;
			contents.push( help );
		}
		body.replaceChildren( ...contents );
		enableJqueryDropTargets( view, panel );
		const active = view.uplinkMbbNativeCollectionsFilter || { filter: 'all', collectionId: 0 };
		refreshActiveFilters( panel, active.filter, active.collectionId );
		updateTreeToggle( panel );
	}

	function showPanelStatus( panel, message, type = 'success' ) {
		window.uplinkMbbToast( message, type );
	}

	async function refreshCollectionState( view, panel ) {
		const state = await request( 'uplink_mbb_native_collections_state' );
		setCollectionData( state.collections );
		config.counts = state.counts || config.counts;
		renderPanelBody( panel, view );
		panel.querySelectorAll( '.uplink-mbb-native-collection-parent' ).forEach( ( select ) => fillCollectionSelect( select, false, true ) );
		document.querySelectorAll( '.uplink-mbb-native-upload-select' ).forEach( ( select ) => fillCollectionSelect( select, true ) );
	}

	function enableDragAssignment( view, panel ) {
		if ( ! config.canAssign ) {
			return;
		}
		const targetAtPoint = ( x, y ) => document.elementFromPoint( x, y )?.closest( '.uplink-mbb-native-collection-filter:not([data-filter="all"])' );
		const markAttachments = () => {
			if ( view.uplinkMbbNativeListMode ) {
				view.el.querySelectorAll( '#the-list tr[id^="post-"]' ).forEach( ( row ) => {
					row.classList.add( 'uplink-mbb-native-list-attachment' );
					row.dataset.id = row.id.replace( /^post-/, '' );
				} );
			}
			view.el.querySelectorAll( '.attachment[data-id], .uplink-mbb-native-list-attachment[data-id]' ).forEach( ( attachment ) => {
				if ( attachment.dataset.uplinkMbbDragReady ) {
					return;
				}
				attachment.dataset.uplinkMbbDragReady = '1';
				attachment.draggable = true;
				if ( ! view.uplinkMbbNativeListMode && $.fn.draggable ) {
					$( attachment ).draggable( {
						appendTo: document.body,
						helper: () => dragAssignmentHelper( view, attachment ),
						revert: 'invalid',
						scope: 'uplink-mbb-native-collections',
						zIndex: 100000,
						start: () => attachment.classList.add( 'uplink-mbb-native-is-dragging' ),
						stop: ( event ) => {
							attachment.classList.remove( 'uplink-mbb-native-is-dragging' );
							const target = targetAtPoint( event.clientX, event.clientY );
							const ids = draggedAttachmentIds( view, attachment );
							if ( target && ids.length ) {
								assignDroppedMedia( view, panel, target, ids );
							}
						},
					} );
				}
			} );
		};
		markAttachments();
		const observer = new MutationObserver( markAttachments );
		observer.observe( view.el, { childList: true, subtree: true } );

		let pointerDrag = null;
		const clearPointerDrag = () => {
			pointerDrag?.attachment.classList.remove( 'uplink-mbb-native-is-dragging' );
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			pointerDrag = null;
		};
		view.el.addEventListener( 'pointerdown', ( event ) => {
			const attachment = attachmentFromTarget( view, event.target );
			if ( ! attachment || 0 !== event.button ) {
				return;
			}
			pointerDrag = {
				attachment,
				ids: draggedAttachmentIds( view, attachment ),
				startX: event.clientX,
				startY: event.clientY,
				active: false,
			};
		} );
		document.addEventListener( 'pointermove', ( event ) => {
			if ( ! pointerDrag ) {
				return;
			}
			if ( ! pointerDrag.active && Math.hypot( event.clientX - pointerDrag.startX, event.clientY - pointerDrag.startY ) > 6 ) {
				pointerDrag.active = true;
				pointerDrag.attachment.classList.add( 'uplink-mbb-native-is-dragging' );
			}
			if ( pointerDrag.active ) {
				const target = targetAtPoint( event.clientX, event.clientY );
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.toggle( 'is-drop-target', item === target ) );
			}
		}, true );
		document.addEventListener( 'pointerup', ( event ) => {
			if ( ! pointerDrag ) {
				return;
			}
			const drag = pointerDrag;
			const target = drag.active ? targetAtPoint( event.clientX, event.clientY ) : null;
			clearPointerDrag();
			if ( target && drag.ids.length ) {
				event.preventDefault();
				event.stopPropagation();
				assignDroppedMedia( view, panel, target, drag.ids );
			}
		}, true );
		document.addEventListener( 'pointercancel', clearPointerDrag, true );

		view.el.addEventListener( 'dragstart', ( event ) => {
			const attachment = attachmentFromTarget( view, event.target );
			if ( ! attachment || ! event.dataTransfer ) {
				return;
			}
			const ids = draggedAttachmentIds( view, attachment );
			event.dataTransfer.effectAllowed = 'copy';
			event.dataTransfer.setData( 'text/plain', JSON.stringify( ids ) );
			attachment.classList.add( 'uplink-mbb-native-is-dragging' );
		} );
		view.el.addEventListener( 'dragend', () => {
			view.el.querySelectorAll( '.uplink-mbb-native-is-dragging' ).forEach( ( item ) => item.classList.remove( 'uplink-mbb-native-is-dragging' ) );
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
		} );
		panel.addEventListener( 'dragover', ( event ) => {
			const target = event.target.closest( '.uplink-mbb-native-collection-filter:not([data-filter="all"])' );
			if ( target ) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'copy';
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.toggle( 'is-drop-target', item === target ) );
			}
		} );
		panel.addEventListener( 'dragleave', ( event ) => {
			if ( ! panel.contains( event.relatedTarget ) ) {
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			}
		} );
		panel.addEventListener( 'drop', async ( event ) => {
			const target = event.target.closest( '.uplink-mbb-native-collection-filter:not([data-filter="all"])' );
			if ( ! target ) {
				return;
			}
			event.preventDefault();
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			let ids = [];
			try {
				ids = JSON.parse( event.dataTransfer.getData( 'text/plain' ) );
			} catch ( error ) {
				return;
			}
			ids = Array.isArray( ids ) ? ids.map( Number ).filter( Boolean ) : [];
			if ( ! ids.length ) {
				return;
			}
			await assignDroppedMedia( view, panel, target, ids );
		} );
	}

	function createPanel( view ) {
		if ( ! view?.el || view.uplinkMbbNativeCollectionsPanel?.isConnected ) {
			return;
		}
		if ( ! view.el.isConnected ) {
			if ( ! view.uplinkMbbNativeCollectionsPending ) {
				view.uplinkMbbNativeCollectionsPending = true;
				window.requestAnimationFrame( () => {
					view.uplinkMbbNativeCollectionsPending = false;
					if ( view.el?.isConnected ) {
						createPanel( view );
					}
				} );
			}
			return;
		}

		const modal = view.el.closest( '.media-modal' );
		if ( modal ) {
			const frameContent = modal.querySelector( '.media-frame-content' );
			if ( frameContent && ! frameContent.contains( view.el ) ) {
				return;
			}
			modal.querySelectorAll( '.uplink-mbb-native-collections-panel' ).forEach( ( existingPanel ) => {
				if ( ! view.el.contains( existingPanel ) ) {
					existingPanel.uplinkMbbResizeObserver?.disconnect();
					existingPanel.parentElement?.classList.remove( 'uplink-mbb-native-collections-browser', 'uplink-mbb-native-collections-collapsed', 'uplink-mbb-native-collections-compact' );
					existingPanel.remove();
				}
			} );
		}

		const libraryRoot = view.el.closest( '#wp-media-grid' );
		const isListMode = Boolean( view.uplinkMbbNativeListMode );
		const isStandaloneLibrary = isListMode || Boolean( libraryRoot && ! view.el.closest( '.media-modal' ) );
		const layoutHost = isStandaloneLibrary ? ( libraryRoot?.closest( '#wpbody-content' ) || document.querySelector( '#wpbody-content' ) ) : view.el;
		if ( isStandaloneLibrary ) {
			document.body.classList.add( 'uplink-mbb-native-collections-active' );
			keepStandalonePanelMounted( layoutHost, view );
		}
		const oldPanel = layoutHost.querySelector( ':scope > .uplink-mbb-native-collections-panel' );
		if ( oldPanel ) {
			oldPanel.uplinkMbbResizeObserver?.disconnect();
			oldPanel.remove();
		}

		if ( isStandaloneLibrary ) {
			layoutHost.classList.add( 'uplink-mbb-native-collections-page' );
		} else {
			layoutHost.classList.add( 'uplink-mbb-native-collections-browser' );
		}

		const panel = document.createElement( 'aside' );
		panel.className = 'uplink-mbb-native-collections-panel is-open';
		panel.setAttribute( 'aria-label', config.strings.collections );

		const heading = document.createElement( 'div' );
		heading.className = 'uplink-mbb-native-collections-heading';
		const title = document.createElement( 'strong' );
		title.textContent = config.strings.collections;
		const collapse = document.createElement( 'button' );
		collapse.type = 'button';
		collapse.className = 'uplink-mbb-native-panel-toggle';
		collapse.setAttribute( 'aria-expanded', 'true' );
		collapse.setAttribute( 'aria-label', config.strings.collapse );
		collapse.innerHTML = '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>';
		const headingActions = document.createElement( 'div' );
		headingActions.className = 'uplink-mbb-native-panel-actions';
		const treeToggle = iconButton( 'uplink-mbb-native-tree-toggle', config.strings.collapseAllCollections, 'editor-contract' );
		treeToggle.querySelector( 'span' ).className = 'uplink-mbb-tree-icon is-close';
		headingActions.append( treeToggle );
		let newCollection = null;
		if ( config.canManage ) {
			newCollection = iconButton( 'uplink-mbb-native-new-collection', config.strings.newCollection, 'category' );
			newCollection.setAttribute( 'aria-expanded', 'false' );
			headingActions.append( newCollection );
		}
		headingActions.append( collapse );
		heading.append( title, headingActions );

		const creationForm = document.createElement( 'form' );
		creationForm.className = 'uplink-mbb-native-collection-form';
		creationForm.hidden = true;
		const nameLabel = document.createElement( 'label' );
		nameLabel.textContent = config.strings.collectionName;
		const nameInput = document.createElement( 'input' );
		nameInput.type = 'text';
		nameInput.required = true;
		nameInput.maxLength = 200;
		nameLabel.append( nameInput );
		const parentLabel = document.createElement( 'label' );
		parentLabel.textContent = config.strings.parentCollection;
		const parentSelect = document.createElement( 'select' );
		parentSelect.className = 'uplink-mbb-native-collection-parent';
		fillCollectionSelect( parentSelect, false, true );
		parentLabel.append( parentSelect );
		const formActions = document.createElement( 'div' );
		formActions.className = 'uplink-mbb-native-collection-form-actions';
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = config.strings.cancel;
		const submit = document.createElement( 'button' );
		submit.type = 'submit';
		submit.className = 'button button-primary';
		submit.textContent = config.strings.createCollection;
		formActions.append( cancel, submit );
		creationForm.append( nameLabel, parentLabel, formActions );

		const status = document.createElement( 'div' );
		status.className = 'uplink-mbb-native-panel-status';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		status.hidden = true;

		const body = document.createElement( 'div' );
		body.className = 'uplink-mbb-native-collections-body';
		const resizer = document.createElement( 'div' );
		resizer.className = 'uplink-mbb-native-panel-resizer';
		resizer.setAttribute( 'role', 'separator' );
		resizer.setAttribute( 'aria-orientation', 'vertical' );
		resizer.setAttribute( 'aria-label', config.strings.resizeCollections );
		resizer.setAttribute( 'aria-valuemin', String( panelWidthMin ) );
		resizer.setAttribute( 'aria-valuemax', String( panelWidthMax ) );
		resizer.setAttribute( 'aria-valuenow', String( panelWidthDefault ) );
		resizer.tabIndex = 0;
		panel.append( heading, creationForm, status, body, resizer );
		layoutHost.prepend( panel );
		if ( isStandaloneLibrary ) {
			keepStandalonePanelInViewport( layoutHost, panel );
		}
		enablePanelResize( view, layoutHost, resizer );
		view.uplinkMbbNativeCollectionsPanel = panel;
		view.uplinkMbbNativeDragAssignment = isStandaloneLibrary && config.canAssign;
		if ( isListMode ) {
			const params = new URLSearchParams( window.location.search );
			const collectionId = Number( params.get( 'uplink_mbb_collection' ) ) || 0;
			const filter = collectionId ? 'collection' : ( params.has( 'uplink_mbb_uncategorized' ) ? 'uncategorized' : 'all' );
			view.uplinkMbbNativeCollectionsFilter = { filter, collectionId };
			[ 'uplink_mbb_collection', 'uplink_mbb_uncategorized' ].forEach( ( name ) => {
				view.el.querySelectorAll( `input[name="${ name }"]` ).forEach( ( input ) => input.remove() );
			} );
			if ( 'all' !== filter ) {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = 'collection' === filter ? 'uplink_mbb_collection' : 'uplink_mbb_uncategorized';
				input.value = 'collection' === filter ? String( collectionId ) : '1';
				view.el.append( input );
			}
		} else {
			view.uplinkMbbNativeCollectionsFilter = { filter: 'all', collectionId: 0 };
		}
		renderPanelBody( panel, view );
		enableFontPreviews( view );
		if ( isStandaloneLibrary ) {
			enableDragAssignment( view, panel );
		}

		if ( newCollection ) {
			const openCollectionForm = ( collection = null, parent = 0 ) => {
				creationForm.hidden = false;
				creationForm.dataset.termId = String( Number( collection?.id ) || 0 );
				nameInput.value = collection?.name || '';
				fillCollectionSelect( parentSelect, false, true, collection );
				parentSelect.value = String( Number( collection?.parent ) || Number( parent ) || 0 );
				newCollection.setAttribute( 'aria-expanded', 'true' );
				nameInput.focus();
			};
			const closeCreationForm = () => {
				creationForm.hidden = true;
				creationForm.dataset.termId = '0';
				creationForm.reset();
				newCollection.setAttribute( 'aria-expanded', 'false' );
			};
			newCollection.addEventListener( 'click', () => {
				if ( creationForm.hidden ) {
					openCollectionForm();
				} else {
					closeCreationForm();
				}
			} );
			cancel.addEventListener( 'click', closeCreationForm );
			creationForm.addEventListener( 'submit', async ( event ) => {
				event.preventDefault();
				const name = nameInput.value.trim();
				if ( ! name ) {
					nameInput.focus();
					return;
				}
				submit.disabled = true;
				try {
					const termId = Number( creationForm.dataset.termId ) || 0;
					await request( 'uplink_mbb_save_collection', { term_id: termId, name, parent: Number( parentSelect.value ) || 0 } );
					await refreshCollectionState( view, panel );
					closeCreationForm();
					showPanelStatus( panel, termId ? config.strings.collectionUpdated : config.strings.collectionCreated );
				} catch ( error ) {
					showPanelStatus( panel, error.message, 'error' );
				} finally {
					submit.disabled = false;
				}
			} );
			panel.addEventListener( 'click', async ( event ) => {
				const actionButton = event.target.closest( '[data-collection-action]' );
				if ( ! actionButton ) {
					return;
				}
				const collection = config.collections.find( ( item ) => Number( item.id ) === Number( actionButton.dataset.collection ) );
				if ( ! collection ) {
					return;
				}
				if ( 'add-child' === actionButton.dataset.collectionAction ) {
					openCollectionForm( null, collection.id );
					return;
				}
				if ( 'edit' === actionButton.dataset.collectionAction ) {
					openCollectionForm( collection );
					return;
				}
				if ( 'delete' !== actionButton.dataset.collectionAction ) {
					return;
				}
				const children = childrenByParent.get( Number( collection.id ) ) || [];
				const confirmation = ( children.length ? config.strings.confirmDeleteWithChildren : config.strings.confirmDelete ).replace( '%s', collection.name );
				if ( ! window.confirm( confirmation ) ) {
					return;
				}
				actionButton.disabled = true;
				try {
					await request( 'uplink_mbb_delete_collection', {
						term_id: collection.id,
						child_action: children.length ? 'promote' : '',
					} );
					if ( 'collection' === view.uplinkMbbNativeCollectionsFilter?.filter && Number( view.uplinkMbbNativeCollectionsFilter.collectionId ) === Number( collection.id ) ) {
						applyFilter( view, 'all', 0 );
					}
					await refreshCollectionState( view, panel );
					showPanelStatus( panel, config.strings.collectionDeleted );
				} catch ( error ) {
					actionButton.disabled = false;
					showPanelStatus( panel, error.message, 'error' );
				}
			} );
		}
		let panelUserToggled = false;
		const setCompactLayout = ( compact ) => {
			layoutHost.classList.toggle( 'uplink-mbb-native-collections-compact', compact );
			if ( compact && ! panelUserToggled ) {
				panel.classList.remove( 'is-open' );
				layoutHost.classList.add( 'uplink-mbb-native-collections-collapsed' );
				collapse.setAttribute( 'aria-expanded', 'false' );
				collapse.setAttribute( 'aria-label', config.strings.expand );
				collapse.querySelector( '.dashicons' ).className = 'dashicons dashicons-arrow-right-alt2';
			} else if ( ! compact && ! panelUserToggled ) {
				panel.classList.add( 'is-open' );
				layoutHost.classList.remove( 'uplink-mbb-native-collections-collapsed' );
				collapse.setAttribute( 'aria-expanded', 'true' );
				collapse.setAttribute( 'aria-label', config.strings.collapse );
				collapse.querySelector( '.dashicons' ).className = 'dashicons dashicons-arrow-left-alt2';
			}
		};
		setCompactLayout( layoutHost.getBoundingClientRect().width < 820 );
		if ( window.ResizeObserver ) {
			const observer = new ResizeObserver( () => {
				setCompactLayout( layoutHost.getBoundingClientRect().width < 820 );
			} );
			observer.observe( layoutHost );
			panel.uplinkMbbResizeObserver = observer;
		}

		collapse.addEventListener( 'click', () => {
			panelUserToggled = true;
			const open = ! panel.classList.contains( 'is-open' );
			panel.classList.toggle( 'is-open', open );
			layoutHost.classList.toggle( 'uplink-mbb-native-collections-collapsed', ! open );
			collapse.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			collapse.setAttribute( 'aria-label', open ? config.strings.collapse : config.strings.expand );
			collapse.querySelector( '.dashicons' ).className = `dashicons ${ open ? 'dashicons-arrow-left-alt2' : 'dashicons-arrow-right-alt2' }`;
			view.controller?.uploader?.refresh?.();
		} );
		panel.addEventListener( 'click', ( event ) => {
			const branchToggle = event.target.closest( '.uplink-mbb-native-branch-toggle' );
			if ( branchToggle ) {
				const item = branchToggle.closest( 'li' );
				const childList = item.querySelector( ':scope > .uplink-mbb-native-collection-tree' );
				const collectionId = Number( item.querySelector( '.uplink-mbb-native-collection-filter' )?.dataset.collection ) || 0;
				const expanded = 'false' === branchToggle.getAttribute( 'aria-expanded' );
				if ( expanded ) {
					collapsedCollections.delete( collectionId );
				} else {
					collapsedCollections.add( collectionId );
				}
				toggleBranch( item, branchToggle, childList, expanded );
				updateTreeToggle( panel );
				return;
			}
			const filterButton = event.target.closest( '.uplink-mbb-native-collection-filter' );
			if ( filterButton ) {
				if ( suppressCollectionClick ) {
					event.preventDefault();
					return;
				}
				applyFilter( view, filterButton.dataset.filter, Number( filterButton.dataset.collection ) || 0 );
			}
		} );
		treeToggle.addEventListener( 'click', () => {
			const parentIds = parentCollectionIds();
			if ( 'expand' === treeToggle.dataset.treeAction ) {
				parentIds.forEach( ( id ) => collapsedCollections.delete( id ) );
			} else {
				parentIds.forEach( ( id ) => collapsedCollections.add( id ) );
			}
			renderPanelBody( panel, view );
		} );
	}

	function updateUploadParams() {
		if ( window.wpUploaderInit?.multipart_params ) {
			window.wpUploaderInit.multipart_params.uplink_mbb_collection = uploadCollection;
		}
		if ( window.uploader?.settings?.multipart_params ) {
			window.uploader.settings.multipart_params.uplink_mbb_collection = uploadCollection;
		}
		uploaders.forEach( ( uploader ) => uploader.param?.( 'uplink_mbb_collection', uploadCollection ) );
	}

	function createDestinationSelect() {
		const select = document.createElement( 'select' );
		select.className = 'uplink-mbb-native-upload-select';
		select.setAttribute( 'aria-label', config.strings.destination );
		fillCollectionSelect( select, true );
		select.value = String( uploadCollection );
		select.addEventListener( 'change', () => {
			uploadCollection = Number( select.value ) || 0;
			document.querySelectorAll( '.uplink-mbb-native-upload-select' ).forEach( ( other ) => {
				if ( other !== select ) {
					other.value = String( uploadCollection );
				}
			} );
			updateUploadParams();
		} );
		return select;
	}

	function addUploadDestination( root ) {
		if ( ! config.canAssign || ! root || root.querySelector( '.uplink-mbb-native-upload-destination' ) ) {
			return;
		}
		const field = document.createElement( 'div' );
		field.className = 'uplink-mbb-native-upload-destination';
		const label = document.createElement( 'label' );
		label.textContent = config.strings.destination;
		label.append( createDestinationSelect() );
		const help = document.createElement( 'p' );
		help.textContent = config.strings.uploadHelp;
		field.append( label, help );
		root.prepend( field );
	}

	function defaultInserterToLibrary( modal ) {
		if ( ! modal || modal.dataset.uplinkMbbDefaultLibraryApplied || ! ( modal.offsetWidth || modal.offsetHeight || modal.getClientRects().length ) ) {
			return;
		}
		const libraryTab = modal.querySelector( '#menu-item-browse, #menu-item-library' );
		const uploadTab = modal.querySelector( '#menu-item-upload' );
		if ( ! libraryTab || ! uploadTab ) {
			return;
		}
		modal.dataset.uplinkMbbDefaultLibraryApplied = '1';
		if ( uploadTab.classList.contains( 'active' ) || 'true' === uploadTab.getAttribute( 'aria-selected' ) ) {
			libraryTab.click();
		}
	}

	function defaultVisibleInsertersToLibrary() {
		document.querySelectorAll( '.media-modal' ).forEach( defaultInserterToLibrary );
	}
	let defaultLibraryPending = false;
	function scheduleDefaultInserter() {
		if ( defaultLibraryPending ) {
			return;
		}
		defaultLibraryPending = true;
		window.requestAnimationFrame( () => {
			defaultLibraryPending = false;
			defaultVisibleInsertersToLibrary();
		} );
	}

	document.addEventListener( 'click', ( event ) => {
		const closingModal = event.target instanceof Element ? event.target.closest( '.media-modal-close' )?.closest( '.media-modal' ) : null;
		if ( closingModal ) {
			delete closingModal.dataset.uplinkMbbDefaultLibraryApplied;
		}
		scheduleDefaultInserter();
	}, true );

	if ( window.MutationObserver ) {
		const modalObserver = new MutationObserver( ( mutations ) => {
			const modalChanged = mutations.some( ( mutation ) => {
				if ( mutation.target instanceof Element && mutation.target.closest( '.media-modal' ) ) {
					return true;
				}
				return Array.from( mutation.addedNodes ).some( ( node ) => node instanceof Element && ( node.matches( '.media-modal' ) || node.querySelector( '.media-modal' ) ) );
			} );
			if ( modalChanged ) {
				scheduleDefaultInserter();
			}
		} );
		modalObserver.observe( document.body, { childList: true, subtree: true } );
	}

	const uploaderPrototype = wp.Uploader?.prototype;
	if ( uploaderPrototype?.init && ! uploaderPrototype.uplinkMbbNativeCollectionsPatched ) {
		uploaderPrototype.uplinkMbbNativeCollectionsPatched = true;
		const originalUploaderInit = uploaderPrototype.init;
		uploaderPrototype.init = function () {
			uploaders.add( this );
			this.param?.( 'uplink_mbb_collection', uploadCollection );
			return originalUploaderInit.apply( this, arguments );
		};
	}

	const browserPrototype = wp.media.view?.AttachmentsBrowser?.prototype;
	if ( browserPrototype?.render && ! browserPrototype.uplinkMbbNativeCollectionsPatched ) {
		browserPrototype.uplinkMbbNativeCollectionsPatched = true;
		const originalBrowserRender = browserPrototype.render;
		browserPrototype.render = function () {
			const result = originalBrowserRender.apply( this, arguments );
			createPanel( this );
			return result;
		};
	}

	const uploaderInlinePrototype = wp.media.view?.UploaderInline?.prototype;
	if ( uploaderInlinePrototype?.render && ! uploaderInlinePrototype.uplinkMbbNativeCollectionsPatched ) {
		uploaderInlinePrototype.uplinkMbbNativeCollectionsPatched = true;
		const originalUploaderRender = uploaderInlinePrototype.render;
		uploaderInlinePrototype.render = function () {
			const result = originalUploaderRender.apply( this, arguments );
			addUploadDestination( this.el.querySelector( '.uploader-inline-content' ) || this.el );
			return result;
		};
	}

	$( () => {
		defaultVisibleInsertersToLibrary();
		const listForm = document.querySelector( 'body.upload-php #posts-filter' );
		if ( listForm && ! document.querySelector( '#wp-media-grid' ) ) {
			createPanel( { el: listForm, uplinkMbbNativeListMode: true } );
		}
		const nativeUpload = document.querySelector( '#plupload-upload-ui' );
		if ( nativeUpload ) {
			addUploadDestination( nativeUpload );
		}
		updateUploadParams();
	} );
}( window.jQuery, window.wp ) );
