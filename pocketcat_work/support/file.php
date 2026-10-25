<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
$tid=(int)getv('thread');
$file=trim(str_replace('\\','/',(string)getv('file')),'/');
$s=db()->prepare('SELECT user_id FROM support_threads WHERE id=?');$s->execute([$tid]);$owner=(int)$s->fetchColumn();$cu=current_user();
if(!$owner)exit('Not found');
$segments=array_values(array_filter(explode('/',$file),fn($x)=>$x!==''&&$x!=='.'&&$x!=='..'));
if(count($segments)!==2||!ctype_digit($segments[0])||!preg_match('/^[A-Za-z0-9._-]+$/',$segments[1]))exit('Not found');
$pathUser=(int)$segments[0];
if(($cu['role']??'')!=='admin' && $owner!==(int)$cu['id']){http_response_code(403);exit('Forbidden');}
if(($cu['role']??'')!=='admin' && $pathUser!==(int)$cu['id']){http_response_code(403);exit('Forbidden');}
$path=__DIR__.'/../uploads/support/'.$pathUser.'/'.$segments[1];
if(!is_file($path)){http_response_code(404);exit('Not found');}
$mime=function_exists('mime_content_type')?(mime_content_type($path)?:'application/octet-stream'):'application/octet-stream';header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.basename($segments[1]).'"');readfile($path);exit;
