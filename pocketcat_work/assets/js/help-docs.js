document.addEventListener('DOMContentLoaded',()=>{
 const input=document.getElementById('pc-doc-search'),sections=[...document.querySelectorAll('.pc-doc-section')],nav=[...document.querySelectorAll('#pc-doc-nav a')];
 const apply=()=>{const q=(input?.value||'').trim().toLowerCase();sections.forEach(s=>{s.hidden=!!q&&!s.dataset.docSearch.includes(q)});nav.forEach(a=>{const target=document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));a.hidden=!!q&&(!target||target.hidden)});};input?.addEventListener('input',apply);
 const focus=()=>{const key=new URLSearchParams(location.search).get('doc')||location.hash.slice(1);if(!key)return;const el=document.getElementById(key);if(el){setTimeout(()=>{el.scrollIntoView({behavior:'smooth',block:'start'});el.classList.add('pc-doc-focus');setTimeout(()=>el.classList.remove('pc-doc-focus'),1800)},80)}};focus();
 nav.forEach(a=>a.addEventListener('click',()=>{nav.forEach(x=>x.classList.remove('active'));a.classList.add('active')}));
 const obs=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting){const a=document.querySelector('#pc-doc-nav a[href="#'+CSS.escape(e.target.id)+'"]');nav.forEach(x=>x.classList.remove('active'));a?.classList.add('active')}})},{rootMargin:'-15% 0px -70% 0px'});sections.forEach(s=>obs.observe(s));
});
