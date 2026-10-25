/* PocketCat Media Library V19.68 interaction safety and dynamic selection fixes. */
(function(){
 'use strict';
 const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
 // A single open action menu at a time; outside clicks and Escape close it.
 document.addEventListener('click',e=>{
   const current=e.target.closest('.asset-menu');
   $$('.asset-menu[open]').forEach(menu=>{if(menu!==current)menu.removeAttribute('open')});
   if(!current)$$('.asset-menu[open]').forEach(menu=>menu.removeAttribute('open'));
 });
 document.addEventListener('toggle',e=>{
   const menu=e.target;if(!menu.matches?.('.asset-menu')||!menu.open)return;
   $$('.asset-menu[open]').forEach(other=>{if(other!==menu)other.removeAttribute('open')});
   const rect=menu.getBoundingClientRect();menu.classList.toggle('flip-up',window.innerHeight-rect.bottom<240&&rect.top>150);
 },true);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')$$('.asset-menu[open]').forEach(menu=>menu.removeAttribute('open'))});
 // Selection always queries the live DOM so newly uploaded cards are included.
 const getChecks=()=>$$('.media-check');
 function syncSelection(){const checks=getChecks(),all=$('#selectAll'),n=checks.filter(x=>x.checked).length;const label=$('#mediaSelectedCount');if(label)label.textContent=n+' item'+(n===1?'':'s')+' selected';if(all){all.indeterminate=n>0&&n<checks.length;all.checked=checks.length>0&&n===checks.length}$$('.media-card').forEach(card=>card.classList.toggle('selected',!!card.querySelector('.media-check:checked')))}
 document.addEventListener('change',e=>{if(e.target.matches('.media-check'))syncSelection()});
 $('#selectAll')?.addEventListener('change',e=>{getChecks().forEach(cb=>{cb.checked=e.target.checked});syncSelection()});
 document.addEventListener('pocketcat:media-added',syncSelection);syncSelection();
 // Bind long-press selection for existing and dynamically inserted cards.
 let timer=null,longPressed=false,startX=0,startY=0;
 document.addEventListener('touchstart',e=>{const card=e.target.closest('.media-card');if(!card||e.target.closest('button,a,input,summary,select,textarea,details'))return;const touch=e.touches[0];startX=touch.clientX;startY=touch.clientY;longPressed=false;clearTimeout(timer);timer=setTimeout(()=>{const cb=card.querySelector('.media-check');if(cb){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));longPressed=true;const t=$('#pcMediaToast');if(t){t.textContent='Selected '+(card.dataset.name||'item');t.hidden=false;clearTimeout(t._timer);t._timer=setTimeout(()=>t.hidden=true,4500)}}},520)},{passive:true});
 ['touchend','touchcancel'].forEach(name=>document.addEventListener(name,()=>clearTimeout(timer),{passive:true}));
 document.addEventListener('touchmove',e=>{if(!timer)return;const touch=e.touches[0];if(Math.abs(touch.clientX-startX)>12||Math.abs(touch.clientY-startY)>12)clearTimeout(timer)},{passive:true});
 document.addEventListener('contextmenu',e=>{if(longPressed){e.preventDefault();longPressed=false}});
 // Avoid native prompt fallbacks: copy actions report a toast and leave a usable URL in the field.
 document.addEventListener('click',async e=>{const b=e.target.closest('[data-copy-url]');if(!b)return;const url=b.dataset.copyUrl;if(!url)return;try{await navigator.clipboard.writeText(url);b.textContent='Copied';setTimeout(()=>{if(b.isConnected)b.textContent=b.dataset.originalLabel||'Copy URL'},1400)}catch(_){const toast=$('#pcMediaToast');if(toast){toast.textContent='Clipboard access was blocked. Open the item to copy its URL from the address bar.';toast.hidden=false;clearTimeout(toast._timer);toast._timer=setTimeout(()=>toast.hidden=true,6000)}}});
 // On tiny viewports, ensure search/filter controls do not force document-level horizontal scrolling.
 const filter=$('.media-filter-form');if(filter){filter.querySelectorAll('input,select,button').forEach(el=>{el.style.minWidth='0';el.style.maxWidth='100%'})}
})();
