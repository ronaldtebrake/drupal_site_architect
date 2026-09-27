/** Export and verify the offline comparison. Node 22+ and Chromium required. */
import { spawn } from 'node:child_process';
import { readFile, writeFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { createHash } from 'node:crypto';

const here=dirname(fileURLToPath(import.meta.url));
const source=join(here,'index.html');
const scratch=await mkdtemp(join(tmpdir(),'architect-comparison-'));
const errors=[],pending=new Map(),checks=[];
let chrome,socket,counter=0;
function command(method,params={}){const id=++counter;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(new Error(method+' timed out'))},30000);pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params}))})}
async function evaluate(expression){const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value}
async function load(query){await command('Page.navigate',{url:pathToFileURL(source).href+query});for(let n=0;n<100;n++){try{if(await evaluate('document.readyState==="complete" && Boolean(window.Comparison) && location.search==='+JSON.stringify(query)))return}catch{}await delay(50)}throw new Error('Page did not load')}
async function layout(){return evaluate(`(()=>{
 const boxes=[...document.querySelectorAll('#poster .step,#poster .brief,#poster header')];
 const bad=[];
 for(const el of document.querySelectorAll('#poster h1,#poster h2,#poster h3,#poster p,#poster .hint,#poster .metric')){
  if(el.scrollWidth>el.clientWidth+1)bad.push('Horizontal text overflow: '+el.textContent);
 }
 for(const box of boxes){const r=box.getBoundingClientRect();for(const child of box.children){const c=child.getBoundingClientRect();if(c.bottom>r.bottom+1||c.right>r.right+1)bad.push('Child outside card: '+child.textContent)}}
 const left=[...document.querySelectorAll('#baseline .step')],right=[...document.querySelectorAll('#scored .step')];
 const aligned=innerWidth<=760||left.every((el,i)=>Math.abs(el.getBoundingClientRect().top-right[i].getBoundingClientRect().top)<2);
 return {case:Comparison.current,width:innerWidth,height:document.querySelector('#poster').getBoundingClientRect().height,overflow:bad,aligned,documentFits:document.documentElement.scrollWidth<=innerWidth,externalResources:performance.getEntriesByType('resource').filter(r=>/^https?:/.test(r.name)).map(r=>r.name)};
})()`)}
try{
 chrome=spawn(process.env.CHROMIUM||'chromium',['--headless=new','--no-sandbox','--disable-dev-shm-usage','--no-first-run','--disable-background-networking','--disable-extensions','--disable-component-update','--disable-sync','--hide-scrollbars','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+join(scratch,'profile'),'about:blank'],{stdio:['ignore','ignore','pipe']});
 let log='';chrome.stderr.on('data',d=>log+=d.toString());
 let port;for(let n=0;n<100;n++){if(chrome.exitCode!==null)throw new Error(log.slice(-1000));try{port=Number((await readFile(join(scratch,'profile','DevToolsActivePort'),'utf8')).split('\n')[0]);break}catch{}await delay(50)}
 if(!port)throw new Error('Chromium did not start');
 const pages=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
 socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true})});
 socket.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails);const w=pending.get(m.id);if(w){clearTimeout(w.timer);pending.delete(m.id);m.error?w.reject(new Error(JSON.stringify(m.error))):w.resolve(m.result)}});
 await command('Page.enable');await command('Runtime.enable');
 for(const id of ['workshops','translation','discussions']){
  await command('Emulation.setDeviceMetricsOverride',{width:1320,height:1600,deviceScaleFactor:1,mobile:false});
  await load('?export=1&case='+id);
  const check=await layout();checks.push(check);
  if(check.overflow.length||!check.aligned||!check.documentFits||check.externalResources.length)throw new Error(JSON.stringify(check));
  const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:1320,height:Math.ceil(check.height),scale:1}});
  await writeFile(join(here,id+'.png'),Buffer.from(shot.data,'base64'));
 }
 await command('Emulation.setDeviceMetricsOverride',{width:1320,height:1200,deviceScaleFactor:1,mobile:false});
 await load('?case=workshops');
 await evaluate('document.querySelector("[data-case=translation]").click()');
 if(await evaluate('Comparison.current')!=='translation')throw new Error('Case button failed');
 await evaluate('document.querySelector(".details summary").click()');
 if(!await evaluate('document.querySelector(".details details").open && document.querySelectorAll("#evidence tbody tr").length===3'))throw new Error('Evidence toggle failed');
 for(const id of ['workshops','translation','discussions']){
  await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  await load('?case='+id);
  const check=await layout();checks.push(check);
  if(check.overflow.length||!check.documentFits)throw new Error(JSON.stringify(check));
 }
 if(errors.length)throw new Error(JSON.stringify(errors));
 const data=JSON.parse(await readFile(join(here,'results.json'),'utf8'));
 for(const c of data.cases){
  if(c.jev.parts.length!==c.baseline.rankings.length)throw new Error('Part count mismatch');
  if(!c.jev.model.startsWith('jev-'))throw new Error('Unexpected provider model');
  const hash=createHash('sha256').update(JSON.stringify(c.pool)).digest('hex');
  if(hash!==c.evidence_sha256)throw new Error('Evidence hash mismatch');
  for(const item of c.baseline.rankings){
   if(item.candidates.some((candidate,i)=>i>0&&candidate.score>item.candidates[i-1].score))throw new Error('Baseline ranking changed');
   if(item.candidates.some(candidate=>!c.pool.some(p=>p.id===candidate.id)))throw new Error('Unknown candidate');
  }
 }
 await writeFile(join(here,'checks.json'),JSON.stringify({source_sha256:createHash('sha256').update(await readFile(source)).digest('hex'),cases:checks,buttons:true,evidence_toggle:true,evidence_hashes:true,script_errors:errors},null,2)+'\n');
 console.log('Verified three desktop exports, three mobile layouts, buttons, evidence toggle, source hashes and rankings.');
}finally{
 for(const w of pending.values())clearTimeout(w.timer);
 socket?.close();chrome?.kill('SIGTERM');await delay(200);await rm(scratch,{recursive:true,force:true});
}
