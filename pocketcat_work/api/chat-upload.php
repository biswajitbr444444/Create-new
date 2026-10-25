<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
if(!function_exists('upload_move_received'))require __DIR__.'/../includes/upload-engine.php';
header('Content-Type: application/json; charset=utf-8');
try{
 $u=current_user();if(function_exists('policy_flag') && !policy_flag('file_uploads',true))upload_json(['ok'=>false,'error'=>'File uploads are temporarily disabled by the administrator.'],503);if($_SERVER['REQUEST_METHOD']!=='POST')upload_json(['ok'=>false,'error'=>'POST required.'],405);verify_csrf();
 $cid=(int)post('conversation_id');$s=db()->prepare('SELECT id FROM conversations WHERE id=? AND user_id=?');$s->execute([$cid,(int)$u['id']]);if(!$s->fetch())upload_json(['ok'=>false,'error'=>'Conversation not found.'],404);
 $fs=$_FILES['files']??($_FILES['file']??null);if(!$fs||empty($fs['tmp_name']))upload_json(['ok'=>false,'error'=>'No file selected.'],422);
 $tmp=is_array($fs['tmp_name'])?$fs['tmp_name']:[$fs['tmp_name']];$names=is_array($fs['name'])?$fs['name']:[$fs['name']];$sizes=is_array($fs['size'])?$fs['size']:[$fs['size']];$errs=is_array($fs['error'])?$fs['error']:[$fs['error']];$out=[];
 $dir=upload_area_root('media',(int)$u['id']).'/chat';if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Chat attachment directory is not writable.');
 foreach($tmp as $i=>$tp){
   $up=['error'=>(int)($errs[$i]??UPLOAD_ERR_NO_FILE),'tmp'=>(string)$tp,'size'=>(int)($sizes[$i]??0),'name'=>(string)($names[$i]??''),'mime'=>(string)($fs['type'][$i]??'application/octet-stream')];
   if($up['size']>upload_request_limit_bytes())upload_json(['ok'=>false,'error'=>'Attachment exceeds the current request limit. Use the resumable uploader for larger files.'],413);
   $fileName=bin2hex(random_bytes(18)).upload_extension($up['name']);$path=$dir.'/'.$fileName;
   upload_move_received($up,$path,$up['size']);$url='/uploads/media/'.(int)$u['id'].'/chat/'.$fileName;
   $mime=function_exists('mime_content_type')?(mime_content_type($path)?:$up['mime']):$up['mime'];$name=file_normalize_name($up['name']);$kind=upload_media_kind($mime,$name);
   $db=db();$db->prepare('INSERT INTO chat_attachments(conversation_id,user_id,name,stored_path,storage_path,mime_type,size_bytes,kind,source_type,source_category,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$cid,(int)$u['id'],$name,'',$url,$mime,$up['size'],$kind,'ai','AI Workspace']);
   $attachmentId=(int)$db->lastInsertId();$public='/api/chat-attachment.php?id='.$attachmentId;$db->prepare('UPDATE chat_attachments SET stored_path=? WHERE id=? AND user_id=?')->execute([$public,$attachmentId,(int)$u['id']]);if(function_exists('asset_catalog_register_chat'))asset_catalog_register_chat((int)$u['id'],$attachmentId,$name,$public,$mime,$up['size'],$kind);
   $out[]=['name'=>$name,'url'=>$public,'kind'=>$kind,'attachment_id'=>$attachmentId];
 }
 if(!$out)upload_json(['ok'=>false,'error'=>'No files were uploaded.'],422);
 upload_json(['ok'=>true,'attachments'=>$out]+$out[0]);
}catch(Throwable $e){upload_fail($e,500);}
