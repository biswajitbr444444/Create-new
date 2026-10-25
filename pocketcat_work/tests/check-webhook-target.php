<?php
declare(strict_types=1);
require __DIR__.'/../includes/functions.php';
function check_webhook(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} echo "PASS: {$message}\n"; }
check_webhook(!webhook_target_is_safe('http://example.com/hook'),'rejects plaintext HTTP webhook');
check_webhook(!webhook_target_is_safe('https://localhost/hook'),'rejects localhost webhook');
check_webhook(!webhook_target_is_safe('https://127.0.0.1/hook'),'rejects loopback IP webhook');
check_webhook(!webhook_target_is_safe('https://192.168.1.5/hook'),'rejects private-network webhook');
check_webhook(!webhook_target_is_safe('https://user:pass@example.com/hook'),'rejects credential-bearing webhook URL');
echo "All webhook target smoke tests passed.\n";
