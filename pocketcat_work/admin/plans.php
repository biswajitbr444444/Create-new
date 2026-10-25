<?php
require __DIR__.'/../bootstrap.php'; require_super_admin();
plan_catalog_no_cache_headers();
$adminId=(int)current_user()['id']; $d=policy_data(); $defaults=policy_defaults();
$d['plan_meta']=array_replace_recursive(policy_defaults()['plan_meta'],$d['plan_meta']??[]); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); try {
  $action=(string)post('action','save');
  if($action==='save') {
   foreach(['free','basic','premium'] as $plan) {
    $name=trim((string)($_POST['meta'][$plan]['name']??$d['plan_meta'][$plan]['name']));
    $price=trim((string)($_POST['meta'][$plan]['price']??$d['plan_meta'][$plan]['price']));
    $period=(string)($_POST['meta'][$plan]['period']??'month'); $desc=trim((string)($_POST['meta'][$plan]['description']??''));
    if($name==='' || mb_strlen($name)>40) throw new RuntimeException('Plan names must be 1–40 characters.');
    if(!preg_match('/^\d{1,7}(\.\d{1,2})?$/',$price)) throw new RuntimeException('Enter a valid non-negative price with up to two decimals.');
    if(!in_array($period,['month','year','one-time'],true)) throw new RuntimeException('Invalid billing period.');
    $enabled=isset($_POST['meta'][$plan]['enabled']); if($plan==='free'&&!$enabled) throw new RuntimeException('The Free plan must remain enabled for new registrations.'); $d['plan_meta'][$plan]=['name'=>$name,'price'=>$price,'period'=>$period,'description'=>mb_substr($desc,0,240),'enabled'=>$enabled];
    foreach(array_keys($defaults['plans'][$plan]) as $key) {
      $default=$defaults['plans'][$plan][$key]; $raw=$_POST['plans'][$plan][$key]??null;
      if(is_bool($default)) $d['plans'][$plan][$key]=($raw==='1');
      elseif(is_int($default) || is_float($default)) { try { $d['plans'][$plan][$key]=policy_validate_numeric_allocation($key,$raw); } catch (InvalidArgumentException $validationError) { throw new RuntimeException($validationError->getMessage().' for '.$plan.'.'); } }
      elseif(is_string($default)) { $v=trim((string)($raw??$default)); if($key==='url_redirect_mode'&&!in_array($v,['301','302','307','308'],true)) throw new RuntimeException('Invalid redirect mode.'); $d['plans'][$plan][$key]=$v; }
    }
   }
   policy_save($d,$adminId,'plans.catalog.update'); flash('success','Plan names, prices, lifecycle and feature limits saved.'); redirect('/admin/plans/');
  }
 } catch(Throwable $e) { $msg=$e->getMessage(); }
}
$title='Subscription Plan Manager'; require __DIR__.'/../includes/header.php';
$planKeys=array_keys($defaults['plans']['free']);
?><div class="page-head"><div><span class="eyebrow">SUPER ADMIN · SUBSCRIPTIONS</span><h1>A to Z Plan Manager</h1><p>Configure public plan metadata, lifecycle and every policy limit. Canonical tier keys are preserved for compatibility; disable a tier rather than deleting its key.</p></div><a class="btn secondary" href="/admin/control-plane/">Policy Control Plane</a></div>
<?php if($msg!==''):?><div class="alert alert-error"><?=e($msg)?></div><?php endif;?>
<form method="post" id="plan-settings-form"><?=csrf_field()?> <input type="hidden" name="action" value="save"><div class="plan-save-sticky"><span>Save plan names, prices, descriptions and all feature allocations.</span><button class="btn" type="submit">Save Plan Settings</button></div>
<div class="grid-main"><?php foreach(['free','basic','premium'] as $plan): $m=$d['plan_meta'][$plan];?><section class="card"><h2><?=e($m['name'])?></h2><div class="form-grid"><label>Public plan name<input name="meta[<?=$plan?>][name]" maxlength="40" required value="<?=e($m['name'])?>"></label><label>Price<input name="meta[<?=$plan?>][price]" inputmode="decimal" required value="<?=e($m['price'])?>"></label><label>Billing period<select name="meta[<?=$plan?>][period]"><?php foreach(['month'=>'Monthly','year'=>'Yearly','one-time'=>'One time'] as $k=>$v):?><option value="<?=$k?>" <?=$m['period']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label><label>Description<input name="meta[<?=$plan?>][description]" maxlength="240" value="<?=e($m['description'])?>"></label></div><label class="check"><input type="checkbox" name="meta[<?=$plan?>][enabled]" value="1" <?=$m['enabled']?'checked':''?>> Enabled for new assignments</label><p class="muted">Unchecked tiers are unavailable for new selection; existing users resolve to Free until re-assigned.</p></section><?php endforeach;?></div>
<section class="card"><h2>Feature and usage allocation</h2><p class="muted">Numeric zero follows existing policy semantics (unlimited for most quotas; verify per feature). Boolean options grant or deny the feature.</p><div class="table-wrap plan-allocation-wrap"><table class="plan-allocation-table"><thead><tr><th>Feature key</th><?php foreach(['free','basic','premium'] as $plan):?><th><?=e($d['plan_meta'][$plan]['name'])?></th><?php endforeach;?></tr></thead><tbody><?php foreach($planKeys as $key):?><tr><td><code><?=e($key)?></code></td><?php foreach(['free','basic','premium'] as $plan):$val=$d['plans'][$plan][$key]??$defaults['plans'][$plan][$key];?><td data-label="<?=e($d['plan_meta'][$plan]['name'])?>"><?php if(is_bool($defaults['plans'][$plan][$key])):?><input aria-label="<?=$plan?> <?=$key?>" type="checkbox" name="plans[<?=$plan?>][<?=$key?>]" value="1" <?=$val?'checked':''?>><?php elseif(is_numeric($defaults['plans'][$plan][$key])):?><input style="min-width:100px;width:100%" type="number" min="0" step="1" name="plans[<?=$plan?>][<?=$key?>]" value="<?=e((string)$val)?>"><?php else:?><input style="min-width:80px;width:100%" name="plans[<?=$plan?>][<?=$key?>]" value="<?=e((string)$val)?>"><?php endif;?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><button class="btn" type="submit">Save Plan Settings</button></section></form>
<script defer src="/assets/js/admin-plan-settings.js?v=19.70-plan-allocation-fix1"></script>
<?php require __DIR__.'/../includes/footer.php'; ?>
