<?php
require __DIR__.'/../bootstrap.php';require_admin();

require_once __DIR__.'/../includes/site-branding-upload.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if(post('action')==='reset'){
  $oldLogo=(string)setting('logo_url','');
  set_setting('app_name','PocketCat');set_app_url('https://pocketcat.online');set_setting('site_meta_description','PocketCat productivity workspace');set_setting('redirect_brand_transition','0');set_setting('logo_url','');set_setting('show_site_name_header','1');
  $oldPath=site_logo_local_path($oldLogo);if($oldPath && is_file($oldPath))@unlink($oldPath);
  flash('success','Site settings restored to defaults.');redirect('/admin/settings/');
 }
 $oldLogo=(string)setting('logo_url','');
 set_setting('app_name',trim((string)post('app_name','')));
 set_app_url((string)post('app_url','https://pocketcat.online'));
 set_setting('show_site_name_header',post('show_site_name_header')==='1'?'1':'0');
 set_setting('redirect_brand_transition',post('redirect_brand_transition')==='1'?'1':'0');
 try {
  if(post('remove_site_logo')==='1') set_setting('logo_url','');
  if(isset($_FILES['site_logo']) && (int)($_FILES['site_logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
   $newLogo=save_uploaded_site_logo($_FILES['site_logo']);
   set_setting('logo_url',$newLogo);
  }
  $newSavedLogo=(string)setting('logo_url','');
  if($oldLogo!=='' && $oldLogo!==$newSavedLogo){$oldPath=site_logo_local_path($oldLogo);if($oldPath && is_file($oldPath))@unlink($oldPath);}
  flash('success','Site settings saved.');
 } catch(Throwable $e) {
  flash('error',$e->getMessage());
 }
 redirect('/admin/settings/');
}
$title='Site Settings';require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Administration</span><h1>Site settings</h1><p>Core public-facing configuration and redirect presentation.</p></div></div>
<p><a class="btn secondary small" href="/admin/site-editor/">Open Live Site Editor</a></p>
<form class="card form-card" id="website-name" method="post" action="/admin/site-settings-save/" enctype="multipart/form-data" accept-charset="UTF-8"><?=csrf_field()?>
<div class="grid2 site-settings-brand-grid">
<label data-help-key="core-site">Website Name<input name="app_name" value="<?=e(setting('app_name','PocketCat'))?>" autocomplete="organization"><small>This may be left blank to show no website title.</small></label>
<label id="website-url" data-help-key="core-site">Website URL<input name="app_url" value="<?=e(setting('app_url','https://pocketcat.online'))?>" required></label>
<div class="site-logo-settings"><label for="site-logo-file">Website Logo <small>PNG, JPG, SVG, or WebP · up to 5 MB</small></label><div class="site-logo-preview" id="site-logo-preview" aria-live="polite"><?php if(trim((string)setting('logo_url',''))!==''): ?><img src="<?=e(setting('logo_url',''))?>" alt="Current website logo" id="site-logo-preview-image" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="brand-mark site-logo-fallback" aria-label="Logo unavailable" hidden>P</span><?php else: ?><span class="brand-mark" id="site-logo-preview-default" aria-label="Default PocketCat icon">P</span><?php endif; ?></div><input id="site-logo-file" type="file" name="site_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp,.png,.jpg,.jpeg,.svg,.webp"><label class="check site-logo-remove"><input type="checkbox" name="remove_site_logo" value="1" id="remove-site-logo"> <span>Remove / Reset Logo (use default icon)</span></label><small class="muted">Uploading a new logo replaces the current custom logo.</small></div>
<div class="site-name-toggle"><label class="check"><input type="checkbox" name="show_site_name_header" value="1" <?=setting('show_site_name_header','1')==='1'?'checked':''?>> <span><strong>Show Website Name in Header</strong><small>When disabled—or when Website Name is blank—only the logo/icon is shown in navigation headers.</small></span></label></div>
</div>
<hr>
<h2>Short-link redirect transition</h2>
<p class="muted">Default is Off. When enabled, intermediate redirect screens can briefly show your PocketCat brand before the visitor is sent to the destination. The page has no manual “Continue” link.</p>
<label class="check redirect-toggle"><input type="checkbox" name="redirect_brand_transition" value="1" <?=setting('redirect_brand_transition','0')==='1'?'checked':''?>> <span>Show PocketCat brand briefly during intermediate redirects</span></label>
<div class="settings-actions"><button class="btn" type="submit">Save Site Settings</button></div>
</form>
<script>
(function(){var input=document.getElementById('site-logo-file'),preview=document.getElementById('site-logo-preview'),remove=document.getElementById('remove-site-logo'),form=document.getElementById('website-name');if(!input||!preview||!form)return;var original=preview.innerHTML;form.addEventListener('submit',async function(ev){ev.preventDefault();var button=form.querySelector('button[type=submit]'),csrf=(new FormData(form)).get('_csrf')||'';if(button){button.disabled=true;button.textContent='Saving…';}try{var file=input.files&&input.files[0],payload={_csrf:csrf,app_name:form.elements.app_name.value,app_url:form.elements.app_url.value,show_site_name_header:form.elements.show_site_name_header.checked?'1':'0',redirect_brand_transition:form.elements.redirect_brand_transition.checked?'1':'0',remove_site_logo:remove&&remove.checked?'1':'0',has_logo_upload:file?'1':'0'};var response=await fetch('/admin/site-settings-save/',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload)});var result=await response.json().catch(function(){return {};});if(!response.ok||!result.ok)throw new Error(result.error||('Settings save failed (HTTP '+response.status+').'));if(file){var logoData=new FormData();logoData.append('_csrf',csrf);logoData.append('site_logo',file,file.name);var logoResponse=await fetch('/admin/site-logo-upload/',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-Token':csrf},body:logoData});var logoResult=await logoResponse.json().catch(function(){return {};});if(!logoResponse.ok||!logoResult.ok)throw new Error(logoResult.error||('Logo upload failed (HTTP '+logoResponse.status+'). Settings were saved, but the logo was not changed.'));}window.location.assign('/admin/settings/?saved=1');}catch(err){alert(err.message||'Could not save site settings.');if(button){button.disabled=false;button.textContent='Save Site Settings';}}});var original=preview.innerHTML;input.addEventListener('change',function(){var f=input.files&&input.files[0];if(!f){preview.innerHTML=original;return;}if(!/^image\/(png|jpeg|svg\+xml|webp)$/.test(f.type)&&!(/\.(png|jpe?g|svg|webp)$/i).test(f.name)){input.value='';preview.innerHTML=original;return;}var url=URL.createObjectURL(f);preview.innerHTML='';var img=document.createElement('img');img.alt='Selected logo preview';img.src=url;img.onload=function(){URL.revokeObjectURL(url)};preview.appendChild(img);if(remove)remove.checked=false;});if(remove)remove.addEventListener('change',function(){if(remove.checked){input.value='';preview.innerHTML='<span class="brand-mark" aria-label="Default PocketCat icon">P</span>';}else{preview.innerHTML=original;}});})();
</script>
<form class="card form-card settings-reset" method="post" action="/admin/site-settings-save/" onsubmit="return confirm('Reset core site settings?')"><?=csrf_field()?><input type="hidden" name="action" value="reset"><button class="btn danger small">Reset to Default</button></form>
<?php require __DIR__.'/../includes/footer.php'; ?>