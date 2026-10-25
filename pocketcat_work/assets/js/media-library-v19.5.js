/* PocketCat Media Library V19.4 responsive interaction guardrails. */
(function(){
 'use strict';
 const root=document.getElementById('media-grid');
 if(!root)return;
 // Keep card content from forcing a wider grid when filenames or metadata are unusually long.
 root.querySelectorAll('.media-card').forEach(card=>{
   card.style.minWidth='0';
   const title=card.querySelector('.media-title-row strong');
   if(title){title.style.overflowWrap='anywhere';title.style.wordBreak='break-word';title.setAttribute('title',title.getAttribute('title')||title.textContent.trim());}
 });
 // Make the search form usable on narrow screens without losing the native GET behavior.
 const filter=document.querySelector('.media-filter-form');
 if(filter){filter.addEventListener('submit',()=>{const submit=filter.querySelector('button[type="submit"],button:not([type])');if(submit){submit.disabled=true;submit.setAttribute('aria-busy','true');submit.textContent='Filtering…';}});}
 // Prevent repeated submissions during upload form POSTs (the resumable uploader intercepts normal submissions).
 document.querySelectorAll('#media-upload form.form-card:not(:first-child)').forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('button[type="submit"],button:not([type])');if(b){b.disabled=true;b.setAttribute('aria-busy','true');b.textContent='Saving…';}}));
})();
