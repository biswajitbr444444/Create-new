<?php
declare(strict_types=1);
$GLOBALS['test_settings']=['testing_mode_enabled'=>'0'];
function setting(string $k, ?string $d=null): ?string { return $GLOBALS['test_settings'][$k] ?? $d; }
function set_setting(string $k,string $v): void { $GLOBALS['test_settings'][$k]=$v; }
require __DIR__.'/../includes/policy.php';
function check(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; }
check(policy_testing_mode()===false,'testing mode defaults off');
$defaults=policy_defaults();
check($defaults['plans']['free']['tasks']===10 && $defaults['plans']['basic']['tasks']===30 && $defaults['plans']['premium']['tasks']===0,'task quotas exist in all three plans');
check($defaults['plans']['free']['ai_seconds']===7200 && $defaults['plans']['basic']['ai_seconds']===21600 && $defaults['plans']['premium']['ai_seconds']===0,'AI allowances exist in all three plans');
set_setting('testing_mode_enabled','1');
check(policy_testing_mode()===true,'testing mode reads explicit on setting');
set_setting('testing_mode_enabled','0');
check(policy_testing_mode()===false,'testing mode reads explicit off setting');
$p=policy_testing_unlimited(['url_monthly_links'=>50,'file_storage_bytes'=>1024,'qr_watermark'=>true,'ads_on_download'=>true]);
check($p['url_monthly_links']===0 && $p['file_storage_bytes']===0,'testing mode clears numeric quota limits');
check($p['qr_watermark']===false && $p['ads_on_download']===false,'testing mode removes plan watermarks and download ads');
echo "All policy smoke tests passed.\n";
