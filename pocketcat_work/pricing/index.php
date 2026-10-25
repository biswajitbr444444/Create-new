<?php
require __DIR__.'/../bootstrap.php';
plan_catalog_no_cache_headers();
$planCatalog=plan_catalog();
public_header('Pricing');
?>
<section class="page-hero"><span class="eyebrow">PLANS</span><h1>Choose the plan that fits your work.</h1><p>Plan names, prices, descriptions and feature allocations are loaded from the current saved subscription policy.</p><?php if(policy_testing_mode()): ?><p class="alert">Testing Mode is enabled: all users receive testing access regardless of the plan displayed.</p><?php endif; ?></section>
<div class="plan-grid public-pricing-plans"><?php foreach(['free','basic','premium'] as $planCardKey): if(empty($planCatalog['plan_meta'][$planCardKey]['enabled'])) continue; $planCardContext='pricing'; require __DIR__.'/../includes/plan-card.php'; endforeach; ?></div>
<?php public_footer(); ?>
