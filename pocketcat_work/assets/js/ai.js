document.addEventListener('DOMContentLoaded',()=>{
 const widget=document.querySelector('[data-ai-widget]'); if(!widget)return; const btn=widget.querySelector('.ai-fab-button'),close=widget.querySelector('[data-ai-close]'),form=widget.querySelector('[data-ai-form]'),box=widget.querySelector('[data-ai-messages]');
 btn.onclick=()=>widget.classList.toggle('open'); close.onclick=()=>widget.classList.remove('open');
 form.addEventListener('submit',async e=>{e.preventDefault();const ta=form.querySelector('textarea'),prompt=ta.value.trim();if(!prompt)return;box.innerHTML+=`<div class="ai-bubble user">${escapeHtml(prompt)}</div>`;ta.value='';const wait=document.createElement('div');wait.className='ai-bubble assistant';wait.textContent='Thinking…';box.appendChild(wait);try{
   const r=await fetch('/api/ai-chat.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'text/event-stream'},body:new URLSearchParams({prompt,stream:'1',_csrf:window.POCKETCAT_CSRF||''})});
   const contentType=r.headers.get('content-type')||'';
   if(r.status===401){location.href='/login/?next='+encodeURIComponent(location.pathname);return;}
   if(!r.body)throw new Error('AI server returned no response body.');
   const reader=r.body.getReader(),decoder=new TextDecoder();let buffer='',received=false;
   while(true){const {value,done}=await reader.read();if(done)break;buffer+=decoder.decode(value,{stream:true});const events=buffer.split('\n\n');buffer=events.pop()||'';for(const event of events){for(const line of event.split('\n')){if(!line.startsWith('data:'))continue;try{const d=JSON.parse(line.slice(5).trim());if(d.error)throw new Error(d.error);if(d.delta){if(!received){wait.textContent='';received=true;}wait.textContent+=d.delta;box.scrollTop=box.scrollHeight;}if(d.done&&!received)wait.textContent='Bedrock returned no text.';}catch(err){wait.textContent=err.message||'AI request failed';}}}}
   if(!received&&contentType.includes('application/json')){throw new Error('Unexpected JSON response from AI server.');}
 }catch(err){wait.textContent=err.message||'AI request failed';}});
 function escapeHtml(s){return s.replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
});
