<?php
require __DIR__.'/../bootstrap.php';
plan_catalog_no_cache_headers();
if(!current_user()){redirect('/login/?next=/billing/');}
$u=current_user(); $currentPlan=(string)($u['plan']??'free');
$planCatalog=plan_catalog();
$title='Plans & Billing'; require __DIR__.'/../includes/header.php';
?>
<?php if(policy_testing_mode()): ?><div class="testing-mode-banner"><strong>Testing Mode / All Features Unlocked</strong><span>All registered users have access to plan-controlled features during testing. No checkout is performed by this page.</span></div><?php endif; ?>
<?php if(billing_sandbox_enabled()): ?><div class="testing-mode-banner" role="status"><strong>Local billing sandbox enabled</strong><span>Checkout is a deterministic simulation. It never charges money or contacts a payment provider.</span></div><?php endif; ?>
<div class="page-head billing-page-head"><div><span class="eyebrow">Plans & Billing</span><h1>Choose your plan</h1><p>Plan names, prices, descriptions and feature allowances use the same saved subscription catalogue as the public pricing page.</p></div><div class="actions"><a class="btn secondary" href="/settings/">Billing Information</a><div class="billing-current-plan"><span>Database plan</span><strong><?=e(ucfirst($currentPlan))?></strong></div></div></div>
<div class="plan-grid billing-plans"><?php foreach(['free','basic','premium'] as $planCardKey): if(empty($planCatalog['plan_meta'][$planCardKey]['enabled'])) continue; $planCardContext='billing'; $planCardCurrent=$currentPlan; require __DIR__.'/../includes/plan-card.php'; endforeach; ?></div>
<p class="muted"><?=billing_sandbox_enabled()?'Local sandbox checkout is available for end-to-end testing; no live payment provider is configured.':'Live checkout and automatic billing are not configured in this build; upgrade requests do not charge a payment method.'?></p>
<?php require __DIR__.'/../includes/footer.php'; ?>
