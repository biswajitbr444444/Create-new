(function(){
  'use strict';
  const root=document.querySelector('[data-links-ui][data-csrf]') || document.querySelector('[data-links-ui]');
  const eventRoot=document;
  if(!root)return;
  const csrf=root.dataset.csrf||'';
  const fileInput=document.getElementById('bulkCsv'), fileName=document.getElementById('bulkCsvName'), fileClear=document.getElementById('bulkCsvClear'), importForm=document.getElementById('bulkCsvForm');
  const allowed=/\.csv$/i;
  const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  function setFileMessage(msg,invalid){if(fileName){fileName.textContent=msg||'No CSV file selected';fileName.classList.toggle('is-invalid',!!invalid);}}
  function clearFile(){if(fileInput)fileInput.value='';setFileMessage('No CSV file selected',false);if(fileClear)fileClear.hidden=true;}
  if(fileInput)fileInput.addEventListener('change',function(){const file=this.files&&this.files[0];if(!file){clearFile();return;}if(!allowed.test(file.name)){setFileMessage('Invalid file — please choose a .csv file.',true);this.value='';if(fileClear)fileClear.hidden=true;return;}setFileMessage(file.name,false);if(fileClear)fileClear.hidden=false;});
  if(fileClear)fileClear.addEventListener('click',clearFile);
  if(importForm)importForm.addEventListener('submit',function(e){const file=fileInput&&fileInput.files&&fileInput.files[0];if(!file){e.preventDefault();setFileMessage('Please choose a CSV file first.',true);return;}if(!allowed.test(file.name)){e.preventDefault();setFileMessage('Invalid file — please choose a .csv file.',true);return;}});

  function toast(message,ok){let el=document.getElementById('linksUiToast');if(!el){el=document.createElement('div');el.id='linksUiToast';el.className='links-toast';document.body.appendChild(el);}el.textContent=message;el.classList.toggle('error',!ok);el.classList.add('show');clearTimeout(el._timer);el._timer=setTimeout(()=>el.classList.remove('show'),1800);}
  function fallbackCopy(text,done){const ta=document.createElement('textarea');ta.value=text;ta.setAttribute('readonly','');ta.style.position='fixed';ta.style.left='-9999px';ta.style.top='0';document.body.appendChild(ta);ta.select();try{if(!document.execCommand('copy'))throw new Error();done();}catch(e){toast('Copy failed — select the URL manually.',false);}finally{ta.remove();}}
  function copyText(text,button){const done=()=>{const old=button.textContent;button.textContent='Copied!';button.classList.add('copied');setTimeout(()=>{button.textContent=old;button.classList.remove('copied')},1200);};if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(text).then(done).catch(()=>fallbackCopy(text,done));else fallbackCopy(text,done);}

  function renderQr(card){
    const box=card.querySelector('[data-qr-box]'); if(!box||box.dataset.rendered==='1')return;
    const url=card.dataset.qrUrl||'';
    if(typeof qrcode!=='function'){box.innerHTML='<div class="qr-unavailable">QR generator unavailable. Open Customize QR.</div>';return;}
    try{const qr=qrcode(0,'H');qr.addData(url);qr.make();box.innerHTML=qr.createSvgTag({cellSize:5,margin:4,scalable:true,alt:url});const svg=box.querySelector('svg');if(svg){svg.removeAttribute('width');svg.removeAttribute('height');svg.setAttribute('viewBox',svg.getAttribute('viewBox')||'0 0 200 200');svg.style.width='min(100%,280px)';svg.style.height='auto';svg.style.display='block';}box.dataset.rendered='1';}catch(e){box.textContent='Unable to generate QR code.';}
  }
  function setQr(card,show){const panel=card.querySelector('.link-qr-panel');const button=card.querySelector('[data-qr-toggle]');if(!panel||!button)return;panel.hidden=!show;if(show)renderQr(card);button.setAttribute('aria-expanded',show?'true':'false');const label=button.querySelector('[data-qr-toggle-label]');if(label)label.textContent=show?'Hide QR code':'Show QR code';}

  function nativeShare(url,title){if(navigator.share){navigator.share({title:title||'PocketCat short link',text:'Open this PocketCat link',url}).catch(()=>{});return;}fallbackCopy(url,()=>toast('Link copied. Native sharing is not available here.',true));}
  function socialShare(service,url){const u=encodeURIComponent(url);if(service==='whatsapp'){window.open('https://wa.me/?text='+u,'_blank','noopener,noreferrer');return;}if(service==='facebook'){window.open('https://www.facebook.com/sharer/sharer.php?u='+u,'_blank','noopener,noreferrer');return;}if(service==='instagram'){fallbackCopy(url,()=>{});window.open('https://www.instagram.com/','_blank','noopener,noreferrer');toast('Link copied. Paste it into Instagram.',true);}}

  eventRoot.addEventListener('click',function(e){
    const copy=e.target.closest('[data-copy]');if(copy){e.preventDefault();copyText(copy.dataset.copy||'',copy);return;}
    const qrToggle=e.target.closest('[data-qr-toggle]');if(qrToggle){e.preventDefault();const card=qrToggle.closest('[data-link-card]');if(card){const expanded=qrToggle.getAttribute('aria-expanded')==='true';setQr(card,!expanded);}return;}
    const native=e.target.closest('[data-native-share]');if(native){e.preventDefault();nativeShare(native.dataset.shareUrl||'',native.dataset.shareTitle||'PocketCat short link');return;}
    const social=e.target.closest('[data-social-share]');if(social){e.preventDefault();socialShare(social.dataset.socialShare,social.dataset.shareUrl||'');return;}
    const edit=e.target.closest('[data-edit-link]');if(edit){e.preventDefault();openEditor(edit);return;}
  });

  document.querySelectorAll('[data-link-card]').forEach(card=>{const panel=card.querySelector('[data-qr-panel]');const button=card.querySelector('[data-qr-toggle]');if(panel&&button&&button.getAttribute('aria-expanded')==='true')setQr(card,true);});

  const modal=document.getElementById('linkEditModal'), form=document.getElementById('linkEditForm');let activeCard=null;
  function openEditor(button){if(!modal||!form)return;activeCard=button.closest('[data-link-card]');form.dataset.id=button.dataset.id||'';form.elements.id.value=button.dataset.id||'';form.elements.destination_url.value=button.dataset.destination||'';form.elements.title.value=button.dataset.title||'';form.elements.expires_at.value=button.dataset.expires||'';form.elements.max_clicks.value=button.dataset.maxClicks||'0';form.elements.status.value=button.dataset.status||'active';modal.hidden=false;document.body.classList.add('modal-open');setTimeout(()=>form.elements.destination_url.focus(),30);}
  function closeEditor(){if(!modal)return;modal.hidden=true;document.body.classList.remove('modal-open');}
  if(modal){modal.addEventListener('click',e=>{if(e.target.matches('[data-close-edit]')||e.target===modal)closeEditor();});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)closeEditor();});}

  async function updateViaApi(id){
    const res=await fetch('/api/admin-links.php?id='+encodeURIComponent(id),{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'},body:JSON.stringify({destination_url:form.elements.destination_url.value,title:form.elements.title.value,expires_at:form.elements.expires_at.value,max_clicks:form.elements.max_clicks.value,status:form.elements.status.value})});
    const data=await res.json().catch(()=>({}));if(!res.ok||!data.data)throw new Error(data.message||data.error||'Unable to update link.');return data.data;
  }
  function applyUpdated(data){if(!activeCard)return;const dest=activeCard.querySelector('[data-url-value]'),short=activeCard.querySelector('[data-short-value]'),copyDest=activeCard.querySelector('[data-copy-destination]'),status=activeCard.querySelector('[data-status]');if(dest)dest.textContent=data.destination_url;if(copyDest)copyDest.dataset.copy=data.destination_url;if(short)short.textContent=data.short_url||short.textContent;if(status){status.className='link-status '+(data.status==='active'?'is-active':'is-inactive');status.innerHTML='<span class="status-dot"></span>'+esc(data.status.charAt(0).toUpperCase()+data.status.slice(1));}activeCard.querySelectorAll('[data-edit-link]').forEach(b=>{b.dataset.destination=data.destination_url;b.dataset.title=data.title||'';b.dataset.expires=data.expires_at?data.expires_at.replace(' ','T').slice(0,16):'';b.dataset.maxClicks=data.max_clicks;b.dataset.status=data.status;});}
  const editParam=new URLSearchParams(window.location.search).get('edit');
  if(editParam){const target=document.querySelector('[data-edit-link][data-id=\"'+CSS.escape(editParam)+'\"]');if(target)setTimeout(()=>openEditor(target),80);}
  if(form)form.addEventListener('submit',async function(e){e.preventDefault();const id=form.elements.id.value||form.dataset.id;if(!id)return;const submit=form.querySelector('[type=submit]');submit.disabled=true;submit.textContent='Saving…';try{const data=await updateViaApi(id);applyUpdated(data);closeEditor();toast('Link updated successfully.',true);}catch(err){
      // Server-side POST is the reliable fallback if fetch/API routing is unavailable.
      submit.disabled=false;submit.textContent='Save changes';toast('Saving through the server…',true);form.submit();return;
    }finally{submit.disabled=false;if(!submit.disabled)submit.textContent='Save changes';}});

  const createForm=document.querySelector('[data-create-link]');
  function linkCardHtml(d,featured){
    const short=String(d.short_url||''); const title=esc(d.title||''); const destination=esc(d.destination_url||'');
    const expires=d.expires_at?String(d.expires_at).replace(' ','T').slice(0,16):'';
    const label=featured?'LATEST SHORT LINK':'SHORT LINK';
    return `<article class="link-card ${featured?'link-card-featured':''} ${featured?'':'link-history-card'}" data-link-card data-link-id="${esc(d.id)}" data-qr-url="${esc(short)}">
      <div class="link-card-top"><div><span class="eyebrow">${label}</span><div class="link-date">Just now</div></div><span class="link-status is-active" data-status><span class="status-dot"></span>Active</span></div>
      <div class="link-url-block"><span class="link-field-label">SHORT URL</span><div class="link-url-line"><a href="${esc(short)}" target="_blank" rel="noopener" data-short-value>${esc(short)}</a><button type="button" class="btn small link-copy" data-copy="${esc(short)}">Copy</button></div></div>
      ${featured?'<div class="link-qr-panel" data-qr-panel><div class="link-qr-wrap" data-qr-box></div><div class="link-qr-caption">Scan to open your short link</div></div>':'<button type="button" class="btn secondary qr-toggle-btn" data-qr-toggle aria-expanded="false"><span class="qr-toggle-icon">▣</span><span data-qr-toggle-label>Show QR code</span></button><div class="link-qr-panel" data-qr-panel hidden><div class="link-qr-wrap" data-qr-box></div><div class="link-qr-caption">Scan to open your short link</div></div>'}
      <div class="link-destination"><span class="link-field-label">DESTINATION</span><a href="${destination}" target="_blank" rel="noopener" data-url-value>${destination}</a></div>
      <div class="link-actions-grid ${featured?'':'history-actions'}"><button type="button" class="btn secondary" data-edit-link data-id="${esc(d.id)}" data-destination="${destination}" data-title="${title}" data-expires="${esc(expires)}" data-max-clicks="${esc(d.max_clicks||0)}" data-status="active">Edit</button><a class="btn secondary" href="/qr/${esc(d.slug)}">Customize QR</a><button type="button" class="btn secondary" data-native-share data-share-url="${esc(short)}" data-share-title="${title||'PocketCat short link'}">Share</button><a class="btn secondary" href="/link-analytics/?id=${esc(d.id)}">Analytics</a><form method="post" action="/links/" onsubmit="return confirm('Delete this link?')"><input type="hidden" name="csrf_token" value="${esc(csrf)}"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${esc(d.id)}"><button class="btn danger" type="submit">Delete</button></form></div>
      <div class="social-share-row"><a class="share-whatsapp" data-social-share="whatsapp" data-share-url="${esc(short)}">WhatsApp</a><a class="share-facebook" data-social-share="facebook" data-share-url="${esc(short)}">Facebook</a><a class="share-instagram" data-social-share="instagram" data-share-url="${esc(short)}">Instagram</a><button type="button" class="device-share" data-native-share data-share-url="${esc(short)}" data-share-title="${title||'PocketCat short link'}">Device share</button></div>
    </article>`;
  }
  function insertCreatedLink(data){
    const featured=document.querySelector('#myQrCode');
    if(featured){
      const old=featured.querySelector('[data-link-card]');
      if(old)old.outerHTML=linkCardHtml(data,true);
      else{
        const empty=featured.querySelector('.empty-state');if(empty)empty.outerHTML=linkCardHtml(data,true);else featured.insertAdjacentHTML('beforeend',linkCardHtml(data,true));
      }
      const qrCard=featured.querySelector('[data-link-card]');if(qrCard){qrCard.dataset.qrUrl=data.short_url;setQr(qrCard,true);}
    }
    const history=document.querySelector('#linkHistory');
    if(history){
      let list=history.querySelector('.link-history-list');
      if(!list){const empty=history.querySelector('.empty-state');if(empty)empty.outerHTML='<div class="link-history-list"></div>';list=history.querySelector('.link-history-list');}
      if(list)list.insertAdjacentHTML('afterbegin',linkCardHtml(data,false));
      const countText=history.querySelector('.section-head-row p');if(countText){const m=countText.textContent.match(/(\d+) recent/);const n=m?parseInt(m[1],10)+1:1;countText.textContent=`${n} recent short links. Each entry has its own QR code, status and quick actions.`;}
    }
    document.querySelectorAll('[data-link-card]').forEach(card=>{if(card.dataset.linkId===String(data.id)&&card.querySelector('[data-qr-toggle]'))setQr(card,false);});
    document.querySelector('#myQrCode')?.scrollIntoView({behavior:'smooth',block:'start'});
  }
  if(createForm)createForm.addEventListener('submit',async function(e){
    e.preventDefault();
    const submit=createForm.querySelector('[type="submit"]');const old=submit?submit.textContent:'';if(submit){submit.disabled=true;submit.textContent='Creating…';}
    try{
      const response=await fetch('/api/create-link/',{method:'POST',body:new FormData(createForm),headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
      const raw=await response.text();
      let data={};
      try{data=JSON.parse(raw);}catch(parseErr){const detail=raw.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,220);throw new Error(detail||('Server returned HTTP '+response.status+'.'));}
      if(!response.ok||!data.ok)throw new Error(data.error||'Unable to create link.');
      insertCreatedLink(data.data);
      createForm.reset();
      const max=createForm.elements.max_clicks;if(max)max.value='0';
      toast('Short link created successfully.',true);
    }catch(err){toast(err.message||'Unable to create link.',false);}finally{if(submit){submit.disabled=false;submit.textContent=old||'Create link';}}
  });
})();
