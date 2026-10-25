<?php
require __DIR__.'/../bootstrap.php';
if(!billing_sandbox_enabled()){http_response_code(404);exit('Not found.');}
if(!current_user()){redirect('/login/?next=/billing/');}

$u=current_user();
$plan=(string)($_POST['plan']??$_GET['plan']??'');
$catalog=plan_catalog();
$meta=$catalog['plan_meta'][$plan]??null;
if(!in_array($plan,['basic','premium'],true)||!is_array($meta)||empty($meta['enabled'])){
    http_response_code(404);
    exit('The selected plan is unavailable.');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $result=billing_sandbox_checkout((int)$u['id'],$plan);
        flash('success','Sandbox payment authorized. No money was charged. Your local account now uses the '.$plan.' plan.');
        redirect('/billing/');
    }catch(Throwable $e){
        error_log('[PocketCat billing sandbox] '.$e->getMessage());
        flash('error','Sandbox checkout could not be completed. No payment was taken and the account plan was not changed.');
        redirect('/billing/sandbox-checkout/?plan='.rawurlencode($plan));
    }
}

$title='Local sandbox checkout';
require __DIR__.'/../includes/header.php';
?>
<section class="center-card">
  <span class="eyebrow">Local test environment</span>
  <h1>Sandbox checkout · <?=e((string)($meta['name']??ucfirst($plan)))?></h1>
  <p class="sub">This deterministic local simulation does not contact a payment provider or charge money.</p>
  <div class="billing-current-plan"><span>Test amount</span><strong>$<?=e((string)$meta['price'])?> / <?=e(plan_period_label((string)($meta['period']??'month')))?></strong></div>
  <form method="post">
    <?=csrf_field()?>
    <input type="hidden" name="plan" value="<?=e($plan)?>">
    <p class="muted">Continuing records a sandbox transaction and changes only this local account's plan.</p>
    <button class="btn full" type="submit">Complete mock checkout</button>
  </form>
  <p><a href="/billing/">Cancel and return to plans</a></p>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
