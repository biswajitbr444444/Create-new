<?php
/** Shared plan card used by the public homepage, /pricing/, and /billing/. */
$planCardKey=(string)($planCardKey??'free');
$planCardMeta=$planCatalog['plan_meta'][$planCardKey]??[];
$planCardValues=$planCatalog['plans'][$planCardKey]??[];
$planCardCurrent=(string)($planCardCurrent??'')===$planCardKey;
$planCardContext=(string)($planCardContext??'public');
$planCardName=(string)($planCardMeta['name']??ucfirst($planCardKey));
$planCardFeatures=plan_card_features($planCardValues);
?>
<article class="card plan-card <?= $planCardKey==='basic'?'featured-plan':'' ?> <?= $planCardCurrent?'plan-active':'' ?>" data-plan-key="<?=e($planCardKey)?>">
  <span class="badge"><?=e(strtoupper($planCardName))?></span>
  <?php if($planCardCurrent): ?><span class="plan-current-badge">Current plan</span><?php endif; ?>
  <div class="price">$<?=e((string)($planCardMeta['price']??'0'))?><small><?=e(plan_period_label((string)($planCardMeta['period']??'month')))?></small></div>
  <p><?=e((string)($planCardMeta['description']??''))?></p>
  <ul class="plan-feature-list"><?php foreach($planCardFeatures as $feature): ?><li><?=e($feature)?></li><?php endforeach; ?></ul>
  <?php if($planCardContext==='billing'): ?>
    <?php if($planCardCurrent): ?><button class="btn secondary full" type="button" disabled>Current plan</button>
    <?php elseif(function_exists('billing_sandbox_enabled')&&billing_sandbox_enabled()): ?><a class="btn full" href="/billing/sandbox-checkout/?plan=<?=rawurlencode($planCardKey)?>">Sandbox checkout</a>
    <?php elseif(policy_testing_mode()): ?><button class="btn full disabled-btn" type="button" disabled>Testing Mode Active</button>
    <?php else: ?><a class="btn full" href="/contact/?subject=Plan%20upgrade%20<?=rawurlencode($planCardName)?>">Request upgrade</a><?php endif; ?>
  <?php else: ?>
    <a class="btn <?= $planCardKey==='free'?'secondary':'' ?> full" href="<?= $planCardKey==='free'?'/signup/?plan=free':($planCardContext==='homepage'?'/pricing/':'/billing/') ?>"><?= $planCardKey==='free'?'Get started':($planCardContext==='homepage'?'View plan →':'View plan') ?></a>
  <?php endif; ?>
</article>
