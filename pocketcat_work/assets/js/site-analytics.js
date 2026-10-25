(function(){
  'use strict';
  var s=document.currentScript||document.querySelector('script[data-site]'); if(!s)return;
  var key=s.getAttribute('data-site'); if(!key)return;
  var endpoint=s.getAttribute('data-endpoint')||'/api/analytics/collect/';
  var consent=s.getAttribute('data-consent')!=='0';
  var storageOk=true;try{localStorage.setItem('__pc_a','1');localStorage.removeItem('__pc_a')}catch(e){storageOk=false}
  if(consent && storageOk && localStorage.getItem('pc_analytics_consent')!=='granted'){
    var b=document.createElement('button');b.type='button';b.textContent='Allow analytics';b.setAttribute('style','position:fixed;left:16px;bottom:16px;z-index:2147483647;padding:10px 14px;border:1px solid #dbe3ef;border-radius:10px;background:#fff;color:#122033;box-shadow:0 8px 30px rgba(0,0,0,.12);font:600 13px system-ui');b.onclick=function(){localStorage.setItem('pc_analytics_consent','granted');b.remove();init()};document.body&&document.body.appendChild(b);return;
  }
  init();
  function init(){
    var vid=get('pc_visitor')||rand();var sid=sessionStorage.getItem('pc_session')||rand();set('pc_visitor',vid);sessionStorage.setItem('pc_session',sid);
    var sent={};function send(name,meta,value){var now=Date.now();if(name==='scroll'&&sent['scroll'+meta.percent])return; if(name==='scroll')sent['scroll'+meta.percent]=1;var u=new URL(location.href),p={site_key:key,event:name,visitor_id:vid,session_id:sid,path:location.pathname,title:document.title,referrer:document.referrer,language:navigator.language||'',screen_width:screen.width,screen_height:screen.height,value:value||null,metadata:meta||{},utm_source:u.searchParams.get('utm_source')||'',utm_medium:u.searchParams.get('utm_medium')||'',utm_campaign:u.searchParams.get('utm_campaign')||'',utm_term:u.searchParams.get('utm_term')||'',utm_content:u.searchParams.get('utm_content')||''};var body=JSON.stringify(p);if(navigator.sendBeacon&&body.length<60000){try{navigator.sendBeacon(endpoint,new Blob([body],{type:'application/json'}));return}catch(e){}}fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:body,keepalive:true,credentials:'same-origin'}).catch(function(){});}
    send('page_view',{});
    var last=Date.now();setInterval(function(){if(document.visibilityState==='visible'){send('heartbeat',{});last=Date.now()}},30000);
    window.addEventListener('scroll',throttle(function(){var h=document.documentElement.scrollHeight-window.innerHeight;var p=h>0?Math.round(window.scrollY/h*100):100;[25,50,75,90,100].forEach(function(x){if(p>=x)send('scroll',{percent:x})})},700),{passive:true});
    document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a,button,[data-analytics-event]');if(!a)return;var ev=a.getAttribute('data-analytics-event')|| (a.tagName==='A' && a.hostname && a.hostname!==location.hostname?'outbound_click':'click');send(ev,{text:(a.innerText||a.getAttribute('aria-label')||'').slice(0,120),href:(a.href||'').slice(0,500)});});
    document.addEventListener('submit',function(e){var f=e.target;if(f&&f.tagName==='FORM')send('form_submit',{form:(f.getAttribute('name')||f.id||'form').slice(0,100)});});
    window.PocketCatAnalytics={track:function(name,meta,value){send(name,meta,value)},conversion:function(name,value){send('conversion',{goal:name||'conversion'},value)}};
  }
  function rand(){return Math.random().toString(36).slice(2)+Date.now().toString(36)}function get(k){try{return localStorage.getItem(k)}catch(e){return null}}function set(k,v){try{localStorage.setItem(k,v)}catch(e){}}function throttle(fn,wait){var t=0;return function(){var n=Date.now();if(n-t>wait){t=n;fn()}}}
})();
