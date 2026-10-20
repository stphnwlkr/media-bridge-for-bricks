const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {File} = require('node:buffer');
const vm = require('node:vm');
const source = readFileSync(process.argv[2] || require('node:path').join(__dirname, '../assets/cimo-optimize.js'), 'utf8');
async function run({mime='image/png', outputBytes=100, outputMime=mime, width=800, animation=false, uploadError='', throwCompression=false, equalResult=false}={}) {
 let compressed=0, canvases=0, uploads=0;
 const input=Buffer.alloc(1000); if(animation){input.writeUInt32BE(0,8);input.write('acTL',12);}
 const plan=[{size:'full',filename:'fixture.'+(mime==='image/png'?'png':'jpg'),mime,url:'/fixture'}];
 const hooks={hasFilter:()=>true,applyFiltersAsync:async(name,initial,converter)=>{
  assert.equal(converter.options.format,mime);
  const result=await converter.convert({quality:0.7});
  if(equalResult&&!result.reason){result.file=new File([input],plan[0].filename,{type:mime});}
  return result;
 }};
 const window={wp:{hooks},addEventListener:()=>{},uplinkMbbCimoConfig:{ajaxUrl:'/ajax',nonce:'plan-nonce',restUrl:'/cimo/',restNonce:'rest-nonce',strings:{failed:'Optimization failed',unavailable:'Unavailable',progress:'Optimizing %1$d of %2$d',complete:'%1$d sizes optimized, %2$d unchanged, %3$d failed.'}},imageCompression:async(file,options)=>{
  compressed++;assert.equal(options.useWebWorker,false);assert.equal(options.alwaysKeepResolution,true);assert.equal(options.fileType,'image/png');
  if(throwCompression)throw new Error('PNG encoder unavailable');
  return new Blob([Buffer.alloc(outputBytes)],{type:outputMime});
 }};
 const context={window,wp:window.wp,File,Blob,FormData,DataView,TextDecoder,URL:{createObjectURL:()=>'/blob',revokeObjectURL:()=>{}},Image:class{naturalWidth=800;naturalHeight=600;async decode(){}},createImageBitmap:async()=>({width,height:600,close(){}}),document:{createElement:()=>{canvases++;return {getContext:()=>({drawImage(){}}),toBlob:(cb,type)=>cb(new Blob([Buffer.alloc(outputBytes)],{type}))};}},fetch:async(url,options)=>{
  if(url==='/ajax'){assert.equal(options.body.get('nonce'),'plan-nonce');return {ok:true,json:async()=>({success:true,data:plan})};}
  if(url==='/fixture')return {ok:true,blob:async()=>new Blob([input],{type:mime})};
  assert.equal(url,'/cimo/attachments/7/upload-size');assert.equal(options.headers['X-WP-Nonce'],'rest-nonce');assert.equal(options.body.get('file').type,mime);assert.equal(options.body.get('size'),'full');uploads++;
  return {ok:!uploadError,json:async()=>uploadError?{message:uploadError}:{success:true}};
 }};
 vm.runInNewContext(source,context);
 const result=await window.uplinkMbbCimo.optimize(7,()=>{});assert.equal(window.uplinkMbbCimo.busy,false);
 return {...result,compressed,uploads,canvases};
}
(async()=>{
 let r=await run();assert.equal(r.optimized,1);assert.equal(r.compressed,1);assert.equal(r.uploads,1);console.log('PASS: PNG compressor keeps format and resolution; uses authenticated Cimo upload.');
 r=await run({mime:'image/jpeg'});assert.equal(r.optimized,1);assert.equal(r.compressed,0);console.log('PASS: JPEG retains the existing Cimo smart-optimization route.');
 for(const outputBytes of [1000,1200]){r=await run({outputBytes});assert.equal(r.unchanged,1);assert.equal(r.uploads,0);}console.log('PASS: Equal or larger outputs stay unchanged without uploads.');
 r=await run({equalResult:true});assert.equal(r.unchanged,1);assert.equal(r.failed,0);assert.equal(r.uploads,0);console.log('PASS: Equal-size optimizer results are unchanged, not failed.');
 r=await run({width:400});assert.equal(r.failed,1);assert.equal(r.uploads,0);assert.match(r.message,/dimensions-changed/);console.log('PASS: Resized output is rejected with a diagnostic.');
 r=await run({outputMime:'image/webp'});assert.equal(r.failed,1);assert.equal(r.uploads,0);console.log('PASS: Format-changing output is rejected.');
 r=await run({animation:true});assert.equal(r.unchanged,1);assert.equal(r.compressed,0);assert.equal(r.uploads,0);console.log('PASS: Animated PNG is left intact.');
 r=await run({throwCompression:true});assert.equal(r.failed,1);assert.match(r.message,/fixture.png: PNG encoder unavailable/);console.log('PASS: Encoder failures include the file and cause.');
 r=await run({uploadError:'REST permission denied'});assert.equal(r.failed,1);assert.match(r.message,/REST permission denied/);console.log('PASS: Save failures include the REST error.');
})().catch(e=>{console.error(e);process.exitCode=1;});
