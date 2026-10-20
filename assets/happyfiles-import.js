( function () {
 'use strict';
 const root = document.getElementById( 'uplink-mbb-happyfiles-import' ), config = window.uplinkMbbImport;
 if ( ! root || ! config ) return;
 const start = root.querySelector( '[data-import-start]' ); if ( ! start ) return;
 const pause = root.querySelector( '[data-import-pause]' ), output = root.querySelector( '[data-import-status]' );
 const media = root.querySelector( '[data-import-media]' ), provider = root.querySelector( '[data-import-provider]' ), retire = root.querySelector( '[data-import-retire]' );
 const sources = Array.from( root.querySelectorAll( '[data-import-source]' ) );
 const clear = root.querySelector( '[data-cleanup-start]' ), confirm = root.querySelector( '[data-cleanup-confirm]' ), reason = root.querySelector( '[data-cleanup-reason]' ), cleanOutput = root.querySelector( '[data-cleanup-status]' );
 let working = false, cleaning = false, paused = false, job = null, ready = false, cleanInfo = null, previewRequest = 0;
 const selected = () => sources.find( source => source.checked && source.dataset.sourceAvailable === '1' );
 const sourceKey = () => sources.find( source => source.checked )?.value || sources[0]?.value;
 async function request( mode, values = {}, cleanup = false ) {
  const body = new FormData(); body.append( 'action', cleanup ? 'uplink_mbb_folder_cleanup' : 'uplink_mbb_folder_import' ); body.append( 'nonce', cleanup ? config.cleanupNonce : config.nonce ); body.append( 'mode', mode );
  Object.entries( values ).forEach( ( [ key, value ] ) => body.append( key, value ) );
  const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } ), result = await response.json();
  if ( ! response.ok || ! result.success ) throw new Error( result.data?.message || config.strings.failed ); return result.data;
 }
 function render() {
  const running = job?.status === 'running', clearRunning = cleanInfo?.job?.status === 'running', busy = working || cleaning;
  if ( clearRunning ) { sources.forEach( source => { source.checked = source.value === cleanInfo.job.source; } ); }
  else if ( job?.id && sources.some( source => source.value === job.source && ( running || source.dataset.sourceAvailable === '1' ) ) ) {
   sources.forEach( source => { source.checked = source.value === job.source; } );
  }
  if ( running ) { media.checked = !! job.media; provider.checked = !! job.select_provider; retire.checked = !! job.retire_source; }
  sources.forEach( source => { source.disabled = ! ready || busy || running || clearRunning || source.dataset.sourceAvailable !== '1'; } );
  start.disabled = ! ready || busy || clearRunning || ( ! running && ! selected() );
  pause.hidden = ! working; media.disabled = ! ready || busy || running || clearRunning || retire.checked; provider.disabled = retire.disabled = ! ready || busy || running || clearRunning;
  const finish = job?.source === selected()?.value && job?.status === 'complete' && job.retire_source && ! job.cleanup_complete && ! job.denied;
  start.textContent = running ? config.strings.resume : finish ? config.strings.finish : config.strings.start;
  clear.disabled = ! ready || busy || running || ! cleanInfo?.available || ! confirm.checked;
  confirm.disabled = ! ready || busy || running || ! cleanInfo?.available;
  clear.textContent = clearRunning ? config.strings.cleanupResume : config.strings.cleanupStart;
  reason.textContent = cleanInfo?.reason || '';
  if ( ! job?.id ) { output.textContent = job?.message || ''; return; }
  const progress = config.strings.progress.replace( '%1$d', job.cursor ).replace( '%2$d', job.total ).replace( '%3$d', ( job.assigned || 0 ) + ( job.existing || 0 ) + ( job.denied || 0 ) );
  if ( job.status === 'complete' ) output.textContent = config.strings.complete.replace( '%1$d', job.created ).replace( '%2$d', job.reused ).replace( '%3$d', job.assigned ).replace( '%4$d', job.denied ).replace( '%5$d', job.existing || 0 );
  else if ( job.status === 'error' ) output.textContent = `${ progress } ${ job.message }`;
  else output.textContent = `${ progress } ${ paused ? config.strings.paused : config.strings.running }`;
 }
 async function loadCleanup() {
  const seq = ++previewRequest, source = sourceKey(); cleanInfo = null; confirm.checked = false; render();
  try { const value = await request( 'preview', { source }, true ); if ( seq !== previewRequest ) return; cleanInfo = value; if ( cleanInfo.job?.status === 'running' ) job = null; render(); }
  catch ( error ) { if ( seq === previewRequest ) { reason.textContent = error.message; } }
 }
 async function cleanup( source, automatic = false ) {
  cleaning = true; render();
  try {
   if ( automatic && ! cleanInfo?.job ) await request( 'retire', { source, job: job.id }, true );
   let state = await request( 'start', { source, confirmed: '1' }, true );
   while ( state.status === 'running' ) { state = await request( 'step', { job: state.id }, true ); cleanOutput.textContent = config.strings.cleanupProgress.replace( '%1$d', state.removed ).replace( '%2$d', state.total ); }
   cleanOutput.textContent = config.strings.cleanupDone;
   window.location.reload();
  } catch ( error ) { cleaning = false; await loadCleanup(); cleanOutput.textContent = error.message; render(); }
 }
 start.addEventListener( 'click', async () => {
  const source = job?.status === 'running' ? job.source : selected()?.value; if ( ! source || working || cleaning || ! ready ) return;
  if ( job?.source === source && job?.status === 'complete' && job.retire_source && ! job.cleanup_complete && ! job.denied ) { await cleanup( source, true ); return; }
  working = true; paused = false; pause.disabled = false; render();
  try {
   job = await request( 'start', { source, include_media: media.checked ? '1' : '0', select_provider: provider.checked ? '1' : '0', retire_source: retire.checked ? '1' : '0' } ); render();
   while ( job.status === 'running' && ! paused ) { job = await request( 'step', { job: job.id } ); render(); }
   working = false; render();
   if ( job.status === 'complete' && job.retire_source && ! job.cleanup_complete && ! job.denied && ! paused ) { cleanInfo = null; await cleanup( source, true ); }
   else await loadCleanup();
  } catch ( error ) { working = false; render(); output.textContent = job?.status === 'running' ? `${ error.message } ${ config.strings.resumeHint }` : error.message; }
 } );
 clear.addEventListener( 'click', () => { if ( confirm.checked && cleanInfo?.available ) cleanup( cleanInfo.job?.source || sourceKey() ); } );
 confirm.addEventListener( 'change', render );
 retire.addEventListener( 'change', () => { if ( job?.status === 'complete' ) job = null; if ( retire.checked ) media.checked = true; render(); } );
 pause.addEventListener( 'click', () => { paused = true; pause.disabled = true; output.textContent = config.strings.pausing; } );
 sources.forEach( source => source.addEventListener( 'change', () => { if ( job?.status !== 'running' ) job = null; cleanOutput.textContent = ''; loadCleanup(); } ) );
 request( 'status' ).then( async value => { job = value; if ( job?.retire_source && ! job.cleanup_complete && sources.some( source => source.value === job.source && source.dataset.sourceAvailable === '1' ) ) retire.checked = true; paused = job?.status === 'running'; ready = true; render(); await loadCleanup(); } ).catch( error => { ready = true; render(); output.textContent = error.message; } );
}() );
