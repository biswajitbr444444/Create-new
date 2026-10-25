<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$cut=date('Y-m-d H:i:s',time()-86400);
$q=db()->prepare("SELECT id,temp_path FROM upload_sessions WHERE status='uploading' AND updated_at<? LIMIT 500");$q->execute([$cut]);$n=0;
foreach($q->fetchAll() as $r){if(!empty($r['temp_path']))@unlink((string)$r['temp_path']);db()->prepare("UPDATE upload_sessions SET status='failed',updated_at=NOW() WHERE id=?")->execute([(int)$r['id']]);$n++;}
echo "Cleaned {$n} abandoned upload sessions.\n";
