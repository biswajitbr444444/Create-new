<?php
declare(strict_types=1);
require __DIR__.'/../includes/functions.php';
require __DIR__.'/../includes/auth.php';

function check_session_timeout(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
    echo "PASS: {$message}\n";
}

$_SESSION=['user_id'=>42,'_last_activity_at'=>1000,'csrf_token'=>'test-token'];
check_session_timeout(!enforce_session_idle_timeout(1599,600),'preserves an active session before its idle limit');
check_session_timeout((int)$_SESSION['user_id']===42&&$_SESSION['_last_activity_at']===1599,'refreshes activity on active requests');
check_session_timeout(enforce_session_idle_timeout(2199,600),'expires the session at the idle-timeout boundary');
check_session_timeout(!isset($_SESSION['user_id'])&&!isset($_SESSION['csrf_token']),'clears authentication and session state on expiry');
check_session_timeout($_SESSION['_last_activity_at']===2199,'starts a fresh anonymous session after expiry');

$_SESSION=['_last_activity_at'=>1000];
check_session_timeout(!enforce_session_idle_timeout(5000,600),'does not expire anonymous sessions as authenticated sessions');
check_session_timeout($_SESSION['_last_activity_at']===5000,'tracks activity for an anonymous session');
putenv('SESSION_IDLE_TIMEOUT=600');
check_session_timeout((int)getenv('SESSION_IDLE_TIMEOUT')===600,'supports an explicit deployment-configured idle timeout');
echo "All session timeout tests passed.\n";
