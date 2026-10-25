<?php
declare(strict_types=1);
$GLOBALS['test_settings']=['testing_mode_enabled'=>'0'];
function setting(string $k, ?string $d=null): ?string { return $GLOBALS['test_settings'][$k] ?? $d; }
function set_setting(string $k,string $v): void { $GLOBALS['test_settings'][$k]=$v; }
require __DIR__.'/../includes/policy.php';
require __DIR__.'/../includes/plan-catalog.php';
function check_ui_catalog(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; }
$doc=policy_defaults();
$doc['plan_meta']['basic']['price']='10';
$doc['plan_meta']['premium']['price']='20';
$doc['plans']['premium']['file_cdn']=true;
set_setting('policy_engine',json_encode($doc));
$GLOBALS['_policy_data_cache']=null;
$catalog=plan_catalog();
check_ui_catalog($catalog['plan_meta']['basic']['price']==='10' && $catalog['plan_meta']['premium']['price']==='20','catalog exposes saved plan prices without hardcoded fallbacks');
check_ui_catalog(in_array('CDN delivery',plan_card_features($catalog['plans']['premium']),true),'shared plan card includes enabled premium features');
$futurePlan=$catalog['plans']['premium']; $futurePlan['future_capability']=true;
check_ui_catalog(in_array('Future Capability',plan_card_features($futurePlan),true),'shared feature renderer gracefully includes newly configured boolean capabilities');
check_ui_catalog(plan_catalog_public_payload()['plans'][1]['price']==='10','public API payload uses the same saved catalogue');
$payload=plan_catalog_public_payload();
check_ui_catalog(($payload['schema_version']??null)===1 && array_key_exists('updated_at',$payload),'public API exposes a versioned, timestamped catalogue contract');
check_ui_catalog(($payload['plans'][1]['slug']??'')==='basic' && ($payload['plans'][1]['billing_interval']??'')==='month' && ($payload['plans'][1]['status']??'')==='active','public API exposes normalized slug, billing interval, and active status fields');
check_ui_catalog(in_array('Advanced analytics',$payload['plans'][2]['features'],true) && in_array('CDN delivery',$payload['plans'][2]['features'],true),'public API exposes all enabled feature flags as human-readable features');
check_ui_catalog(isset($payload['plans'][2]['allocations']['tasks']) && isset($payload['plans'][2]['allocations']['url_api']),'public API preserves raw quota and feature allocations for clients');
$doc['plan_meta']['basic']['price']='12';
policy_save($doc);
check_ui_catalog(plan_catalog()['plan_meta']['basic']['price']==='12','saving policy invalidates and refreshes the in-request catalogue cache');
check_ui_catalog(($GLOBALS['test_settings']['policy_engine_updated_at']??'')!=='','saving policy refreshes the catalogue update timestamp');
echo "All shared plan catalogue tests passed.\n";
