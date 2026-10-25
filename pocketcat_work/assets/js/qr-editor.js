(function(){
  'use strict';
  const cfg=window.POCKETCAT_QR||{}; const d=cfg.design||{}; const $=id=>document.getElementById(id);
  const box=$('qrBox'), stage=document.querySelector('.qr-preview-stage'), state=$('qrSaveState'); let lastSvg='';
  let localBg=d.background_image||'', localFg=d.foreground_image||'';
  const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const num=(id,fallback)=>{const v=parseFloat($(id).value);return Number.isFinite(v)?v:fallback;};
  const setOut=(id,val)=>{const o=$(id);if(o)o.textContent=val;};
  function updateLabels(){
    setOut('qrFgXOut',num('qrFgX',50)+'%');setOut('qrFgYOut',num('qrFgY',50)+'%');setOut('qrFgScaleOut',Math.round(num('qrFgScale',.22)*100)+'%');setOut('qrFgOpacityOut',Math.round(num('qrFgOpacity',1)*100)+'%');
    setOut('qrBgXOut',num('qrBgX',50)+'%');setOut('qrBgYOut',num('qrBgY',50)+'%');setOut('qrBgScaleOut',Math.round(num('qrBgScale',1)*100)+'%');setOut('qrBgOpacityOut',Math.round(num('qrBgOpacity',.18)*100)+'%');
    if($('qrFgValue'))$('qrFgValue').textContent=$('qrFg').value;if($('qrBgValue'))$('qrBgValue').textContent=$('qrBg').value;
  }
  function actualSize(){const v=$('qrSize').value;if(v!=='auto')return Math.min(2048,Math.max(128,parseInt(v,10)));const w=stage?stage.clientWidth:600;return Math.min(1024,Math.max(280,w-32));}
  function imageTag(url,size,x,y,scale,opacity,mode){if(!url)return '';const iw=size*scale;const ix=size*(x/100)-iw/2, iy=size*(y/100)-iw/2;return '<image href="'+esc(url)+'" x="'+ix.toFixed(2)+'" y="'+iy.toFixed(2)+'" width="'+iw.toFixed(2)+'" height="'+iw.toFixed(2)+'" preserveAspectRatio="'+(mode==='cover'?'xMidYMid slice':'xMidYMid meet')+'" opacity="'+Math.max(0,Math.min(1,opacity)).toFixed(2)+'" />';}
  function render(){
    updateLabels(); if(typeof qrcode!=='function'||!box)return;
    const size=actualSize(), margin=num('qrMargin',4), fg=$('qrFg').value, bg=$('qrBg').value;
    const qr=qrcode(0,'H'); qr.addData(String(cfg.url||'')); qr.make(); const n=qr.getModuleCount();
    const quiet=Math.max(0,Math.min(32,margin)), cell=size/(n+quiet*2); let modules='';
    for(let r=0;r<n;r++)for(let c=0;c<n;c++)if(qr.isDark(r,c)){const x=(c+quiet)*cell,y=(r+quiet)*cell;modules+='<rect x="'+x.toFixed(2)+'" y="'+y.toFixed(2)+'" width="'+(cell+.2).toFixed(2)+'" height="'+(cell+.2).toFixed(2)+'" />';}
    const bgImg=localBg||$('qrBackgroundUrl').value.trim(); const fgImg=localFg;
    let svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+size+'" height="'+size+'" viewBox="0 0 '+size+' '+size+'" role="img" aria-label="QR code for '+esc(cfg.url||'')+'"><rect width="100%" height="100%" fill="'+esc(bg)+'"/>';
    if(bgImg)svg+=imageTag(bgImg,size,num('qrBgX',50),num('qrBgY',50),num('qrBgScale',1),num('qrBgOpacity',.18),'cover');
    svg+='<g fill="'+esc(fg)+'">'+modules+'</g>';
    const logo=$('qrLogo').value.trim(); if(logo)svg+=imageTag(logo,size,50,50,.24,.98,'contain');
    if(fgImg)svg+=imageTag(fgImg,size,num('qrFgX',50),num('qrFgY',50),num('qrFgScale',.22),num('qrFgOpacity',1),'contain');
    svg+='</svg>'; lastSvg=svg; box.innerHTML=svg; const el=box.querySelector('svg'); if(el){el.style.width='min(100%, '+size+'px)';el.style.height='auto';}
    if($('qrActualSize'))$('qrActualSize').textContent=size+' px';
    if(state)state.textContent='Unsaved';
  }
  function collect(){return {version:2,fg:$('qrFg').value,bg:$('qrBg').value,size:$('qrSize').value,margin:num('qrMargin',4),logo_url:$('qrLogo').value.trim(),foreground_image:localFg,background_image:localBg||$('qrBackgroundUrl').value.trim(),foreground_opacity:num('qrFgOpacity',1),background_opacity:num('qrBgOpacity',.18),foreground_x:num('qrFgX',50),foreground_y:num('qrFgY',50),foreground_scale:num('qrFgScale',.22),background_x:num('qrBgX',50),background_y:num('qrBgY',50),background_scale:num('qrBgScale',1),foreground_mode:'center',background_mode:'cover'};}
  async function save(){const form=new FormData();form.append('link_id',String(cfg.link_id));form.append('design',JSON.stringify(collect()));const bg=$('qrBackgroundFile').files[0],fg=$('qrForegroundFile').files[0];if(bg)form.append('background_image',bg);if(fg)form.append('foreground_image',fg);if(state)state.textContent='Saving…';try{const res=await fetch('/api/qr-design.php',{method:'POST',headers:{'X-CSRF-Token':window.POCKETCAT_CSRF||''},body:form});const data=await res.json().catch(()=>({}));if(!res.ok||!data.ok)throw new Error(data.message||'Unable to save QR design.');const saved=data.data.design||{};if(saved.background_image)localBg=saved.background_image;if(saved.foreground_image)localFg=saved.foreground_image;$('qrBackgroundFile').value='';$('qrForegroundFile').value='';if($('qrBackgroundUrl'))$('qrBackgroundUrl').value=localBg||'';if(state)state.textContent='Saved';render();}catch(e){if(state)state.textContent='Save failed';alert(e.message||'Unable to save QR design.');}}
  function reset(){Object.entries({qrFg:d.fg||'#111827',qrBg:d.bg||'#ffffff',qrSize:d.size||'auto',qrMargin:d.margin??4,qrLogo:d.logo_url||'',qrFgX:d.foreground_x??50,qrFgY:d.foreground_y??50,qrFgScale:d.foreground_scale??.22,qrFgOpacity:d.foreground_opacity??1,qrBgX:d.background_x??50,qrBgY:d.background_y??50,qrBgScale:d.background_scale??1,qrBgOpacity:d.background_opacity??.18,qrBackgroundUrl:d.background_image||''}).forEach(([id,v])=>{if($(id))$(id).value=v});localBg=d.background_image||'';localFg=d.foreground_image||'';updateLabels();render();}
  function download(type){const blob=new Blob([lastSvg],{type:'image/svg+xml;charset=utf-8'});if(type==='svg'){const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='pocketcat-qr-'+cfg.slug+'.svg';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);return;}const img=new Image();img.onload=function(){const n=actualSize(),c=document.createElement('canvas');c.width=c.height=n;const ctx=c.getContext('2d');ctx.fillStyle=$('qrBg').value;ctx.fillRect(0,0,n,n);ctx.drawImage(img,0,0,n,n);const a=document.createElement('a');a.href=c.toDataURL('image/png');a.download='pocketcat-qr-'+cfg.slug+'.png';a.click();};img.src=URL.createObjectURL(blob);}
  function share(){if(navigator.share){navigator.share({title:'PocketCat QR',text:'Open this PocketCat link',url:cfg.url}).catch(()=>{});}else alert('Native sharing is not available on this browser. Use one of the social buttons.');}
  document.querySelectorAll('#qrFg,#qrBg,#qrSize,#qrMargin,#qrLogo,#qrBackgroundUrl,#qrFgX,#qrFgY,#qrFgScale,#qrFgOpacity,#qrBgX,#qrBgY,#qrBgScale,#qrBgOpacity').forEach(el=>{el.addEventListener('input',render);el.addEventListener('change',render);});
  $('qrGenerate').onclick=render;$('qrSave').onclick=save;$('qrReset').onclick=reset;$('qrSvg').onclick=()=>download('svg');$('qrPng').onclick=()=>download('png');$('qrShareNative').onclick=share;
  const enc=encodeURIComponent(cfg.url||'');$('qrShareWhatsApp').href='https://wa.me/?text='+enc;$('qrShareTelegram').href='https://t.me/share/url?url='+enc+'&text='+encodeURIComponent('PocketCat link');$('qrShareX').href='https://twitter.com/intent/tweet?url='+enc;$('qrShareFacebook').href='https://www.facebook.com/sharer/sharer.php?u='+enc;
  function uploadPreview(input,setter){input&&input.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.size>3*1024*1024){alert('Maximum image size is 3 MB.');this.value='';return;}const r=new FileReader();r.onload=()=>{setter(r.result);render();};r.readAsDataURL(f);});}
  uploadPreview($('qrBackgroundFile'),v=>{localBg=v;});uploadPreview($('qrForegroundFile'),v=>{localFg=v;});
  window.addEventListener('resize',()=>{if($('qrSize').value==='auto')render();});
  updateLabels();render();
})();
