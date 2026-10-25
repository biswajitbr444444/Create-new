<?php
require __DIR__.'/../bootstrap.php'; require_super_admin();
$admin=current_user(); $d=policy_data(); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $action=(string)post('action','');
 try{
  if($action==='save'){
   $d['flags']['allow_user_byod']=isset($_POST['flags']['allow_user_byod']);
   foreach(['free','basic','premium'] as $plan){
    foreach(['code_max_chars','code_max_versions','code_history_days','code_max_expiry_hours','code_preview_runs_per_hour'] as $k)
      $d['plans'][$plan][$k]=max(0,(int)($_POST['plans'][$plan][$k]??$d['plans'][$plan][$k]));
    foreach(['code_password','code_burn','code_export_all','code_watermark','code_private','code_api','code_priority_worker'] as $k)
      $d['plans'][$plan][$k]=isset($_POST['plans'][$plan][$k]);
   }
   foreach(['code_default_font_size','code_tab_size'] as $k)$d['global'][$k]=max(1,(int)($_POST['global'][$k]??$d['global'][$k]));
   $d['global']['code_default_language']=preg_replace('/[^a-z0-9_-]/i','',(string)($_POST['global']['code_default_language']??'text'));
   $d['global']['code_line_numbers']=isset($_POST['global']['code_line_numbers']);
   $d['global']['code_pastes_per_minute']=max(1,(int)($_POST['global']['code_pastes_per_minute']??20));
   $d['global']['code_preview_per_hour']=max(1,(int)($_POST['global']['code_preview_per_hour']??30));
   $d['global']['code_block_keywords']=array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/',(string)($_POST['global']['code_block_keywords']??'')))));
   foreach(['code_author_country_override','code_author_city_override'] as $k)$d['global'][$k]=trim((string)($_POST['global'][$k]??''));
   foreach(['accent','button_gradient_start','button_gradient_end','light_bg','dark_bg','font_family'] as $k)$d['code_theme'][$k]=trim((string)($_POST['theme'][$k]??$d['code_theme'][$k]));
   foreach(['name','logo','favicon','support_email','og_title','og_description','og_image'] as $k)$d['branding'][$k]=trim((string)($_POST['branding'][$k]??$d['branding'][$k]));
   policy_save($d,(int)$admin['id'],'code.governance.update'); $msg='Code governance saved and live immediately.';
  }
 }catch(Throwable $e){$msg=$e->getMessage();}
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Code Governance</title>
<style>
body{font:14px system-ui;margin:0;background:#f4f6fa;color:#172033}.wrap{max-width:1400px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.card{background:#fff;border:1px solid #dce2eb;border-radius:14px;padding:16px}.plan h2{margin-top:0}.row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:8px 0}.field{display:grid;gap:5px;margin:8px 0}.field input,.field select,textarea{width:100%;box-sizing:border-box;padding:9px;border:1px solid #ccd4e0;border-radius:8px}.checks label{display:block;margin:8px 0}.save{position:sticky;bottom:12px;background:#172033;color:#fff;border:0;border-radius:10px;padding:12px 18px;font-weight:800;cursor:pointer}.msg{padding:12px;background:#e8f7ed;border-radius:8px;margin-bottom:12px}.section{margin-top:16px}@media(max-width:900px){.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}}

@media(max-width:1100px){.wrap{padding:20px}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.wrap{padding:14px}.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.card{padding:14px}.save{width:100%}}
.grid>*{min-width:0}.field input,.field select,textarea{max-width:100%;min-width:0;box-sizing:border-box}.checks{overflow-wrap:anywhere}
</style></head><body><main class="wrap"><h1>Code Editor Governance</h1><p>Dynamic policy controls. Changes are database-backed and apply without redeployment.</p><?php if($msg):?><div class="msg"><?=h($msg)?></div><?php endif;?>
<form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save">
<section class="grid">
<?php foreach(['free'=>'Free','basic'=>'Basic','premium'=>'Premium'] as $plan=>$label):$p=$d['plans'][$plan];?>
<div class="card plan"><h2><?=$label?></h2>
<div class="row"><div class="field"><label>Max characters (0=unlimited)</label><input type="number" name="plans[<?=$plan?>][code_max_chars]" value="<?=h($p['code_max_chars'])?>"></div><div class="field"><label>Max versions (0=unlimited)</label><input type="number" name="plans[<?=$plan?>][code_max_versions]" value="<?=h($p['code_max_versions'])?>"></div></div>
<div class="row"><div class="field"><label>History days (0=lifetime)</label><input type="number" name="plans[<?=$plan?>][code_history_days]" value="<?=h($p['code_history_days'])?>"></div><div class="field"><label>Max expiry hours (0=unlimited)</label><input type="number" name="plans[<?=$plan?>][code_max_expiry_hours]" value="<?=h($p['code_max_expiry_hours'])?>"></div></div>
<div class="field"><label>Preview runs/hour (0=unlimited)</label><input type="number" name="plans[<?=$plan?>][code_preview_runs_per_hour]" value="<?=h($p['code_preview_runs_per_hour'])?>"></div>
<div class="checks"><?php foreach(['code_password'=>'Password protection','code_burn'=>'Burn after read','code_export_all'=>'All language exports','code_watermark'=>'Shared/QR watermark','code_private'=>'Private snippets','code_api'=>'REST API','code_priority_worker'=>'Priority worker'] as $k=>$lab):?><label><input type="checkbox" name="plans[<?=$plan?>][<?=$k?>]" <?=$p[$k]?'checked':''?>> <?=$lab?></label><?php endforeach;?></div>
</div><?php endforeach;?></section>
<section class="grid section"><div class="card"><h2>Storage & BYOD</h2><label><input type="checkbox" name="flags[allow_user_byod]" <?php if(!empty($d['flags']['allow_user_byod'])):?>checked<?php endif;?>> Allow User BYOD / Personal Firebase (E2EE)</label><p>Enables client-side encrypted Personal Firebase storage. Firebase configuration is stored only in the user's encrypted browser vault.</p></div><div class="card"><h2>Global Editor Defaults</h2><div class="field"><label>Default language</label><input name="global[code_default_language]" value="<?=h($d['global']['code_default_language'])?>"></div><div class="row"><div class="field"><label>Font size</label><input type="number" name="global[code_default_font_size]" value="<?=h($d['global']['code_default_font_size'])?>"></div><div class="field"><label>Tab spaces</label><input type="number" name="global[code_tab_size]" value="<?=h($d['global']['code_tab_size'])?>"></div></div><label><input type="checkbox" name="global[code_line_numbers]" <?=$d['global']['code_line_numbers']?'checked':''?>> Show line numbers</label><div class="field"><label>Code pastes/IP/user per minute</label><input type="number" name="global[code_pastes_per_minute]" value="<?=h($d['global']['code_pastes_per_minute'])?>"></div><div class="field"><label>Global preview runs/hour</label><input type="number" name="global[code_preview_per_hour]" value="<?=h($d['global']['code_preview_per_hour'])?>"></div></div>
<div class="card"><h2>Safety & Location</h2><div class="field"><label>Blocked code keywords/patterns</label><textarea name="global[code_block_keywords]" rows="8"><?=h(implode("\n",(array)$d['global']['code_block_keywords']))?></textarea></div><div class="field"><label>Author country override</label><input name="global[code_author_country_override]" value="<?=h($d['global']['code_author_country_override'])?>"></div><div class="field"><label>Author city override</label><input name="global[code_author_city_override]" value="<?=h($d['global']['code_author_city_override'])?>"></div></div></section>
<section class="grid section"><div class="card"><h2>Theme</h2><?php foreach(['accent'=>'Accent','button_gradient_start'=>'Gradient start','button_gradient_end'=>'Gradient end','light_bg'=>'Light background','dark_bg'=>'Dark background','font_family'=>'Font family'] as $k=>$lab):?><div class="field"><label><?=$lab?></label><input name="theme[<?=$k?>]" value="<?=h($d['code_theme'][$k])?>"></div><?php endforeach;?></div>
<div class="card"><h2>Branding</h2><?php foreach(['name'=>'Platform title','logo'=>'Header logo URL','favicon'=>'Favicon URL','support_email'=>'Support email','og_title'=>'OG title','og_description'=>'OG description','og_image'=>'OG image URL'] as $k=>$lab):?><div class="field"><label><?=$lab?></label><input name="branding[<?=$k?>]" value="<?=h($d['branding'][$k])?>"></div><?php endforeach;?></div>
<div class="card"><h2>Live status</h2><p>Policy version: <?=h($d['version'])?></p><p>Admin authorization: Super Admin only</p><p>Client policy endpoint: <code>/api/code/?action=policy</code></p><p>Server validation: enabled</p><p>Runtime changes: enabled</p></div></section><p><button class="save">Save & Publish Policies</button></p></form></main></body></html>
