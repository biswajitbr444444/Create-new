<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
if(!function_exists('upload_move_received'))require __DIR__.'/../includes/upload-engine.php';
header('Content-Type: application/json; charset=utf-8');
function support_attachment_list(?string $raw): array {if(!$raw)return[];$j=json_decode($raw,true);if(is_array($j))return array_values(array_filter($j,'is_string'));return[$raw];}
function support_message_payload(array $m):array{$m['attachments']=support_attachment_list($m['attachment_paths']??null);if(!$m['attachments']&&!empty($m['attachment_path']))$m['attachments']=[$m['attachment_path']];return$m;}
function support_access_thread(int $tid,array $u):?array{$q=($u['role']??'')==='admin'?db()->prepare('SELECT * FROM support_threads WHERE id=?'):db()->prepare('SELECT * FROM support_threads WHERE id=? AND user_id=?');if(($u['role']??'')==='admin')$q->execute([$tid]);else$q->execute([$tid,(int)$u['id']]);return$q->fetch()?:null;}
try{
 $u=current_user();if($_SERVER['REQUEST_METHOD']!=='POST')upload_json(['ok'=>false,'error'=>'POST required.'],405);verify_csrf();$uid=(int)$u['id'];$action=(string)post('action','messages');
 if($action==='messages'){$tid=(int)post('thread_id');$t=support_access_thread($tid,$u);if(!$t)upload_json(['ok'=>false,'error'=>'Thread not found.'],404);$q=db()->prepare('SELECT id,sender_user_id,sender_role,body,attachment_path,attachment_paths,created_at FROM support_messages WHERE thread_id=? ORDER BY id');$q->execute([$tid]);$rows=[];foreach($q->fetchAll() as $m)$rows[]=support_message_payload($m);upload_json(['ok'=>true,'thread'=>$t,'messages'=>$rows]);}
 if($action==='send'){
  $tid=(int)post('thread_id');$t=support_access_thread($tid,$u);if(!$t)upload_json(['ok'=>false,'error'=>'Thread not found.'],404);
  $body=trim((string)post('body',''));$role=($u['role']??'')==='admin'?'admin':'user';$attachments=[];$attachmentMeta=[];
  $tokens=$_POST['upload_tokens']??[];if(!is_array($tokens))$tokens=[$tokens];
  foreach($tokens as $token){$q=db()->prepare("SELECT public_path,original_name,mime_type,size_bytes FROM upload_sessions WHERE user_id=? AND token=? AND scope='support' AND context_id=? AND status='completed' LIMIT 1");$q->execute([$uid,trim((string)$token),$tid]);$x=$q->fetch();if(!$x)upload_json(['ok'=>false,'error'=>'One or more uploaded attachments are invalid.'],422);$attachments[]=(string)$x['public_path'];$attachmentMeta[]=['name'=>(string)$x['original_name'],'mime'=>(string)$x['mime_type'],'size'=>(int)$x['size_bytes'],'url'=>(string)$x['public_path']];}
  $files=$_FILES['files']??($_FILES['file']??null);
  if($files&&isset($files['tmp_name'])){
   if(function_exists('policy_flag') && !policy_flag('file_uploads',true)) upload_json(['ok'=>false,'error'=>'File uploads are temporarily disabled by the administrator.'],503);
   $tmp=$files['tmp_name'];$names=$files['name'];$sizes=$files['size'];$errs=$files['error'];if(!is_array($tmp)){$tmp=[$tmp];$names=[$names];$sizes=[$sizes];$errs=[$errs];}
   $dir=upload_area_root('support',$uid);
   foreach($tmp as $i=>$tp){$up=['error'=>(int)($errs[$i]??UPLOAD_ERR_NO_FILE),'tmp'=>(string)$tp,'size'=>(int)($sizes[$i]??0),'name'=>(string)($names[$i]??''),'mime'=>(string)($files['type'][$i]??'application/octet-stream')];if($up['size']>upload_request_limit_bytes())upload_json(['ok'=>false,'error'=>'Attachment exceeds the current request limit. Use the resumable uploader for larger files.'],413);$file=bin2hex(random_bytes(18)).upload_extension($up['name']);upload_move_received($up,$dir.'/'.$file,$up['size']);$url='/uploads/support/'.$uid.'/'.$file;$attachments[]=$url;$attachmentMeta[]=['name'=>file_normalize_name($up['name']),'mime'=>(string)$up['mime'],'size'=>(int)$up['size'],'url'=>$url];}
  }
  if($body===''&&!$attachments)upload_json(['ok'=>false,'error'=>'Message or attachment is required.'],422);
  $attachment=$attachments[0]??null;$paths=$attachments?json_encode($attachments,JSON_UNESCAPED_SLASHES):null;$q=db()->prepare("INSERT INTO support_messages(thread_id,sender_user_id,sender_role,body,attachment_path,attachment_paths,attachment_category,created_at) VALUES(?,?,?,?,?,?,?,NOW())");$q->execute([$tid,$uid,$role,$body,$attachment,$paths,$attachments?'Support Attachments':null]);$id=(int)$db->lastInsertId();
  if(function_exists('asset_catalog_register_support') && $attachmentMeta){foreach($attachmentMeta as $meta)asset_catalog_register_support($uid,$id,(string)$meta['name'],(string)$meta['url'],(string)$meta['mime'],(int)$meta['size'],'file');}
  db()->prepare('UPDATE support_threads SET updated_at=NOW() WHERE id=?')->execute([$tid]);upload_json(['ok'=>true,'id'=>$id,'message_id'=>$id,'sender_role'=>$role,'body'=>$body,'attachment_path'=>$attachment,'attachments'=>$attachments,'created_at'=>date('Y-m-d H:i:s')]);
 }
 upload_json(['ok'=>false,'error'=>'Unknown action.'],400);
}catch(Throwable $e){upload_fail($e,500);}
