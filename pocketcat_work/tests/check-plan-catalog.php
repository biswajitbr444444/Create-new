<?php
declare(strict_types=1);
$GLOBALS['test_settings']=['testing_mode_enabled'=>'0'];
function setting(string $k, ?string $d=null): ?string { return $GLOBALS['test_settings'][$k] ?? $d; }
function set_setting(string $k,string $v): void { $GLOBALS['test_settings'][$k]=$v; }
require __DIR__.'/../includes/policy.php';
function check_catalog(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; }
$catalog=['plans'=>['free'=>['tasks'=>10],'basic'=>['tasks'=>30],'premium'=>['tasks'=>0]],'plan_meta'=>['free'=>['enabled'=>true],'basic'=>['enabled'=>false],'premium'=>['enabled'=>true]]];
set_setting('policy_engine',json_encode($catalog));
check_catalog(policy_plan('free')['tasks']===10,'enabled Free plan preserves configured limits');
check_catalog(policy_plan('basic')['tasks']===10,'disabled tier safely falls back to Free limits');
check_catalog(policy_plan('unknown')['tasks']===10,'unknown tier safely falls back to Free limits');
check_catalog(policy_validate_numeric_allocation('file_storage_bytes', 1099511627776) === 1099511627776, '1 TiB Premium storage allocation passes validation');
check_catalog(policy_validate_numeric_allocation('file_max_bytes', '21474836480') === 21474836480, '20 GiB maximum-file allocation passes validation');
check_catalog(policy_allocation_max('file_storage_bytes') === 2199023255552, 'byte quota maximum is aligned to 2 TiB');
$decimalRejected = false;
try { policy_validate_numeric_allocation('file_storage_bytes', '1099511627776.5'); } catch (InvalidArgumentException $e) { $decimalRejected = true; }
check_catalog($decimalRejected, 'fractional byte allocations are rejected rather than truncated');
$oversizeRejected = false;
try { policy_validate_numeric_allocation('file_storage_bytes', '2199023255553'); } catch (InvalidArgumentException $e) { $oversizeRejected = true; }
check_catalog($oversizeRejected, 'byte allocations above the supported maximum are rejected');
echo "All plan catalog tests passed.\n";
