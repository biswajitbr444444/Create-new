<?php
declare(strict_types=1);
require __DIR__.'/../includes/functions.php';
require __DIR__.'/../includes/policy.php';
function check_plan(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; }
$free=plan_limits('free');$basic=plan_limits('basic');$premium=plan_limits('premium');
check_plan($free['tasks']===10 && $free['projects']===10 && $free['notes']===10,'Free task/project/note quotas enforced in policy limits');
check_plan($basic['tasks']===30 && $basic['projects']===30 && $basic['notes']===30,'Basic task/project/note quotas enforced in policy limits');
check_plan($premium['tasks']===PHP_INT_MAX && $premium['projects']===PHP_INT_MAX && $premium['notes']===PHP_INT_MAX,'Premium task/project/note quotas map zero to unlimited');
check_plan($free['ai_seconds']===7200 && $basic['ai_seconds']===21600 && $premium['ai_seconds']===PHP_INT_MAX,'AI daily allowances map correctly across tiers');
echo "All plan limit smoke tests passed.\n";
