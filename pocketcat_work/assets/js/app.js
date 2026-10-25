document.addEventListener('DOMContentLoaded',()=>{
 const body=document.body, isMobile=()=>window.innerWidth<801;
 const backdrop=()=>document.querySelectorAll('.sidebar-backdrop');
 const close=()=>{body.classList.remove('menu-open');backdrop().forEach(x=>x.setAttribute('aria-hidden','true'));document.querySelectorAll('[data-sidebar-toggle]').forEach(b=>b.setAttribute('aria-expanded','false'));};
 const open=()=>{if(!isMobile())return;body.classList.add('menu-open');backdrop().forEach(x=>x.setAttribute('aria-hidden','false'));document.querySelectorAll('[data-sidebar-toggle]').forEach(b=>b.setAttribute('aria-expanded','true'));};
 document.querySelectorAll('[data-sidebar-toggle]').forEach(b=>b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();isMobile()?(body.classList.contains('menu-open')?close():open()):close();}));
 document.querySelectorAll('[data-sidebar-close]').forEach(b=>b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();close();}));
 document.querySelectorAll('#app-sidebar a').forEach(a=>a.addEventListener('click',()=>{if(isMobile())close();}));
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 window.addEventListener('resize',()=>{if(!isMobile())close();});
 const path=location.pathname.replace(/\/+$/,'')||'/';document.querySelectorAll('#app-sidebar a').forEach(a=>{const href=new URL(a.href,location.origin).pathname.replace(/\/+$/,'')||'/';if(href===path)a.classList.add('active');});
 const stats=document.querySelector('[data-live-stats]');
 async function refreshStats(){if(!stats)return;try{const r=await fetch('/api/stats.php',{cache:'no-store'});const d=await r.json();stats.querySelectorAll('[data-stat]').forEach(el=>{const k=el.dataset.stat;if(d[k]!==undefined)el.textContent=Number(d[k]).toLocaleString();});}catch(e){}}
 if(stats){refreshStats();setInterval(refreshStats,3000)}
 const chart=document.getElementById('home-growth-chart');if(chart){let v=Number(chart.dataset.growthBase||10000);const rr=(chart.dataset.growthRates||'5,10,15,25,10').split(',').map(Number);const rates=[0,...rr.map(x=>x/100)];const labels=[];for(let i=5;i>=0;i--){const dt=new Date();dt.setMonth(dt.getMonth()-i);labels.push({label:dt.toLocaleString('en',{month:'short'}),value:Math.round(v)});v*=1+rates[5-i]}const latest=Number(chart.dataset.latestMonth||0);if(latest>0&&labels.length)labels[labels.length-1].value=latest;labels[labels.length-1].label='Latest';const max=Math.max(...labels.map(x=>x.value));chart.innerHTML=labels.map(x=>`<div class="chart-col"><span class="chart-value">${x.value.toLocaleString()}</span><div class="bar" style="height:${Math.max(8,Math.round(x.value/max*100))}%"></div><span class="chart-label">${x.label}</span></div>`).join('')}
 const themeBtn=document.querySelector('[data-theme-toggle]');const applyTheme=t=>{document.documentElement.dataset.theme=t;localStorage.setItem('pocketcat-theme',t);if(themeBtn)themeBtn.textContent=t==='dark'?'☀️':'🌙'};const defaultTheme=document.documentElement.dataset.theme||'light',saved=localStorage.getItem('pocketcat-theme');applyTheme(saved||defaultTheme);themeBtn?.addEventListener('click',()=>applyTheme((document.documentElement.dataset.theme||'light')==='dark'?'light':'dark'));
 document.addEventListener('click',e=>{const el=e.target.closest('[data-copy]');if(el){navigator.clipboard?.writeText(el.dataset.copy||el.textContent||'');const old=el.textContent;el.textContent='Copied';setTimeout(()=>el.textContent=old,1200)}});
 // Public mobile drawer
 const pdrawer=document.querySelector('[data-public-drawer]'), pback=document.querySelector('[data-public-backdrop]');
 const pclose=()=>{if(!pdrawer)return;document.body.classList.remove('public-menu-open');pback?.setAttribute('aria-hidden','true');document.querySelector('[data-public-menu-toggle]')?.setAttribute('aria-expanded','false');};
 const popen=()=>{if(!pdrawer)return;document.body.classList.add('public-menu-open');pback?.setAttribute('aria-hidden','false');document.querySelector('[data-public-menu-toggle]')?.setAttribute('aria-expanded','true');};
 document.querySelector('[data-public-menu-toggle]')?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();document.body.classList.contains('public-menu-open')?pclose():popen();});pback?.addEventListener('click',pclose);pdrawer?.querySelectorAll('a').forEach(a=>a.addEventListener('click',pclose));document.addEventListener('keydown',e=>{if(e.key==='Escape')pclose()});
});

// V11.40 analytics/review copy helpers
(function(){
  if(window.__pcAnalyticsCopyBound)return; window.__pcAnalyticsCopyBound=true;
  document.addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('[data-copy-snippet],[data-copy-value]'); if(!b)return;
    var text='';
    if(b.hasAttribute('data-copy-snippet')){var code=b.parentElement&&b.parentElement.querySelector('code');text=code?code.textContent:'';}
    else {var input=b.parentElement&&b.parentElement.querySelector('input');text=input?input.value:'';}
    if(!text)return;
    navigator.clipboard&&navigator.clipboard.writeText(text).then(function(){var old=b.textContent;b.textContent='Copied';setTimeout(function(){b.textContent=old},1400)}).catch(function(){window.prompt('Copy this value:',text)});
  });
})();
