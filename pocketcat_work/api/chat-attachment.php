<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
$uid=(int)current_user()['id'];
$id=(int)($_GET['id']??0);
$q=db()->prepare('SELECT a.* FROM chat_attachments a JOIN conversations c ON c.id=a.conversation_id WHERE a.id=? AND a.user_id=? AND c.user_id=? LIMIT 1');
$q->execute([$id,$uid,$uid]);
$a=$q->fetch();
if(!$a){http_response_code(404);exit('Not found');}
$raw=(string)($a['storage_path']??'');
if($raw==='' && preg_match('~^/uploads/media/'.preg_quote((string)$uid,'~').'/chat/([A-Za-z0-9._-]+)$~',(string)$a['stored_path'],$m))$raw='/uploads/media/'.$uid.'/chat/'.$m[1];
$path=__DIR__.'/..'.parse_url($raw,PHP_URL_PATH);
$root=realpath(__DIR__.'/../uploads/media/'.$uid.'/chat');
$real=is_file($path)?realpath($path):false;
if(!$root||!$real||!str_starts_with($real,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)){http_response_code(404);exit('Not found');}
$mime=function_exists('mime_content_type')?(mime_content_type($real)?:$a['mime_type']):$a['mime_type'];
header('Content-Type: '.preg_replace('/[\r\n]/','',$mime));
header('Content-Length: '.filesize($real));
$download=isset($_GET['download'])&&$_GET['download']==='1';
header('Content-Disposition: '.($download?'attachment':'inline').'; filename="'.addcslashes(basename((string)$a['name']),'\\"').'"');
header('X-Content-Type-Options: nosniff');
readfile($real);exit;
