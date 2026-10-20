(()=>{
'use strict';
const config=window.uplinkMbbConversion;if(!config)return;
let current=null,job=null,results={},urls=[],worker=null,sequence=0,busy=false,reloadAfter=false;
const root=document.getElementById('uplink-mbb-conversion-controls');if(!root)return;
const text=config.strings;
const label=format=>({webp:'WebP',avif:'AVIF',jxl:'JPEG XL',png:'PNG',jpg:'JPEG'}[format]||format);
const el=(tag,content,cls)=>{const node=document.createElement(tag);if(content)node.textContent=content;if(cls)node.className=cls;return node;};
const bytes=n=>new Intl.NumberFormat(undefined,{maximumFractionDigits:1}).format(n/1024)+' KB';
async function request(mode,data={},file=null){
 const form=new FormData();for(const [key,value]of Object.entries({action:'uplink_mbb_image_conversion',nonce:config.nonce,mode,attachment_id:current.id,...data}))form.append(key,value);
 if(file)form.append('file',file,'converted.'+data.format);
 const response=await fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:form});const body=await response.json();
 if(!response.ok||!body.success)throw new Error(body.data?.message||text.failed);return body.data;
}
function clean(){urls.forEach(URL.revokeObjectURL);urls=[];if(worker){worker.terminate();worker=null;}job=null;results={};}
function localUrl(blob){const url=URL.createObjectURL(blob);urls.push(url);return url;}
function encode(blob,format,quality,source,preview){
 if(!worker)worker=new Worker(config.worker,{type:'module'});
 const id=++sequence;
 return new Promise((resolve,reject)=>{
  const handler=event=>{if(event.data.id!==id)return;worker.removeEventListener('message',handler);worker.removeEventListener('error',error);event.data.error?reject(new Error(event.data.error)):resolve(event.data);};
  const error=()=>{worker.removeEventListener('message',handler);reject(new Error(text.encoderFailed));};
  worker.addEventListener('message',handler);worker.addEventListener('error',error,{once:true});worker.postMessage({id,blob,format,quality,preview,width:source.width,height:source.height});
 });
}
let notice,compare,compress,restore,cards,quality,overlay,overlayStatus,previousFocus;
const inertState=new Map();
function status(message){notice.textContent=message;if(overlayStatus)overlayStatus.textContent=message;}
function progressOverlay(value){
 const dialog=root.closest('.uplink-mbb-attachment-dialog');if(!dialog)return;
 if(!overlay){
  overlay=el('div','','uplink-mbb-conversion-overlay');overlay.hidden=true;overlay.tabIndex=-1;
  const panel=el('div','','uplink-mbb-conversion-overlay-panel');
  const spinner=el('span','','uplink-mbb-conversion-spinner');spinner.setAttribute('aria-hidden','true');
  overlayStatus=el('p','','uplink-mbb-conversion-overlay-status');overlayStatus.setAttribute('role','status');overlayStatus.setAttribute('aria-live','polite');overlayStatus.setAttribute('aria-atomic','true');
  panel.append(spinner,el('h3',text.processing),overlayStatus,el('p',text.keepOpen,'uplink-mbb-conversion-overlay-help'));overlay.append(panel);dialog.append(overlay);
  overlay.addEventListener('keydown',event=>{if(event.key==='Tab')event.preventDefault();});
 }
 overlay.hidden=!value;
 if(value){
  previousFocus=document.activeElement;overlayStatus.textContent=text.preparing;
  for(const child of dialog.children){if(child!==overlay){inertState.set(child,child.inert);child.inert=true;}}
  overlay.focus({preventScroll:true});
 }else{
  for(const [child,inert]of inertState)child.inert=inert;inertState.clear();
  const target=previousFocus?.isConnected&&!previousFocus.disabled?previousFocus:root.querySelector('button:not(:disabled)');target?.focus({preventScroll:true});
 }
}
function locked(value){busy=value;root.setAttribute('aria-busy',String(value));notice?.setAttribute('aria-live',value?'off':'polite');root.querySelectorAll('button,input').forEach(node=>node.disabled=value);progressOverlay(value);document.dispatchEvent(new CustomEvent('uplink-mbb-conversion-busy',{detail:value}));}
async function action(fn){if(current?.saveBeforeConvert&&!await current.saveBeforeConvert()){status(text.failed);return;}locked(true);try{await fn();}catch(error){status(error.message);}finally{locked(false);if(reloadAfter){reloadAfter=false;window.location.reload();}}}
function render(media,force=false){
 if(busy&&!force)return;
 clean();root.classList.remove('is-comparing');current=media;root.replaceChildren();root.hidden=!media.canConvert;if(root.hidden)return;
 root.append(el('h4',text.title));
 root.append(el('p',text.help));
 notice=el('p','','uplink-mbb-conversion-status');notice.setAttribute('role','status');notice.setAttribute('aria-live','polite');
 if(media.conversion){
  root.append(el('p',text.saved.replace('%s',label(media.conversion.format))));
  const info=media.conversion;
  root.append(el('p',bytes(info.before)+' → '+bytes(info.after)+' · '+(100*(info.before-info.after)/info.before).toFixed(1)+'% '+text.savings.toLowerCase()+' · '+info.sizes+' '+text.files));
  root.append(el('p',text.quality+': '+(info.quality||text.automatic)+' · '+text.date+': '+info.date));
  if(info.rows?.length){
   const table=el('table'),head=el('thead'),tr=el('tr');[text.size,text.before,text.after,text.savings].forEach(t=>tr.append(el('th',t)));head.append(tr);table.append(head);const body=el('tbody');
   for(const row of info.rows){const line=el('tr');[row.size+' · '+row.width+'×'+row.height,bytes(row.before),bytes(row.after),(100*(row.before-row.after)/row.before).toFixed(1)+'%'].forEach(value=>line.append(el('td',value)));body.append(line);}table.append(body);root.append(table);
  }
  restore=el('button',text.restore,'button');restore.type='button';restore.addEventListener('click',()=>action(async()=>{
   status(text.restoring);await request('restore');status(text.restored);reloadAfter=true;
  }));root.append(restore,notice);return;
 }
 quality=el('div','','uplink-mbb-conversion-quality');
 for(const [format,value]of [['webp',80],['avif',50],['jxl',75],...(media.mime==='image/jpeg'?[['jpg',80]]:[])]){
  const field=el('label',label(format));const input=el('input');input.type='number';input.min='1';input.max='100';input.value=value;input.dataset.format=format;field.append(input);quality.append(field);
 }
 root.append(quality,el('p',text.qualityHelp));
 compare=el('button',text.compare,'button button-primary');compare.type='button';compare.addEventListener('click',()=>action(()=>compareFormats(['webp','avif','jxl'])));
 root.append(compare);
 if(media.canCompress){compress=el('button',text.compress,'button');compress.type='button';compress.addEventListener('click',()=>action(()=>compareFormats([media.mime==='image/jpeg'?'jpg':media.mime.split('/')[1]])));root.append(compress);}
 cards=el('div','','uplink-mbb-conversion-cards');root.append(notice,cards);
}
async function compareFormats(formats){
 clean();root.classList.add('is-comparing');cards.replaceChildren();status(text.preparing);job=await request('begin');if(job.restore)throw new Error(text.restoreFirst);
 const source=job.files.full,qualities=Object.fromEntries([...quality.querySelectorAll('input')].map(input=>[input.dataset.format,Math.max(1,Math.min(100,Number(input.value)||75))]));
 const response=await fetch(source.url,{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error(text.fetchFailed);const original=await response.blob();
 let done=0;
 for(const format of formats){
  const result={files:{},total:0,quality:qualities[format]||80};results[format]=result;
  const card=el('section','','uplink-mbb-conversion-card');card.append(el('h4',label(format)));cards.append(card);
  try{
   status(text.previewProgress.replace('%1$d',++done).replace('%2$d',formats.length));
   const encoded=await encode(original,format,result.quality,source,true);
   let blob=encoded.blob;if(blob.type===original.type&&blob.size>=original.size)blob=original;
   result.files.full=blob;result.total=blob.size;
   const image=el('img');image.src=localUrl(encoded.preview||blob);image.alt=label(format)+' '+text.preview;card.append(image);
   const link=el('a',text.openPreview);link.href=image.src;link.target='_blank';link.rel='noopener';card.append(link);
   const saving=100*(source.bytes-result.total)/source.bytes;
   card.append(el('p',text.previewTotal.replace('%1$s',bytes(result.total)).replace('%2$s',saving.toFixed(1)).replace('%%','%')));
   if(format==='jxl')card.append(el('p',text.jxlHelp));
   const apply=el('button',format==='jxl'?text.useJxl:(current.mime===('image/'+(format==='jpg'?'jpeg':format))?text.applyCompression:text.convert.replace('%s',label(format))),'button');apply.type='button';
   apply.addEventListener('click',()=>action(()=>save(format)));card.append(apply);
  }catch(error){delete results[format];card.append(el('p',error.message));}
  finally{if(worker){worker.terminate();worker=null;}}
 }
 status(Object.keys(results).length?text.ready:text.failed);
}
async function save(format){
 const result=results[format];if(!job||!result)throw new Error(text.compareFirst);
 await request('validate',{token:job.token});
 const entries=Object.entries(job.files);
 try{
  for(let i=0;i<entries.length;i++){
   const [size,source]=entries[i];let file=result.files[size];
   if(!file){
    status(text.progress.replace('%1$d',i+1).replace('%2$d',entries.length));
    const response=await fetch(source.url,{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error(text.fetchFailed);
    const original=await response.blob(),encoded=await encode(original,format,result.quality,source,false);file=encoded.blob;
    if(file.type===original.type&&file.size>=original.size)file=original;
   }
   status(text.saving.replace('%1$d',i+1).replace('%2$d',entries.length));
   await request('stage',{token:job.token,size,format,quality:result.quality},file);
  }
  status(text.updating);const saved=await request('commit',{token:job.token});
  render({...current,conversion:saved.summary},true);document.dispatchEvent(new CustomEvent('uplink-mbb-conversion-complete',{detail:{id:current.id}}));
  status(text.complete.replace('%1$d',saved.sizes).replace('%2$d',saved.references));
 }finally{if(worker){worker.terminate();worker=null;}}
}
document.addEventListener('uplink-mbb-attachment-shown',event=>render(event.detail));
window.addEventListener('beforeunload',event=>{if(busy){event.preventDefault();event.returnValue='';}});
})();
