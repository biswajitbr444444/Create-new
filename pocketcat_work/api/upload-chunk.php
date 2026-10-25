<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
if(!function_exists('upload_session_create')) require __DIR__.'/../includes/upload-engine.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $u=current_user(); $uid=(int)$u['id'];
    if(function_exists('policy_flag') && !policy_flag('file_uploads',true)) upload_json(['ok'=>false,'error'=>'File uploads are temporarily disabled by the administrator.'],503);
    $method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
    if($method!=='POST') upload_json(['ok'=>false,'error'=>'POST required.'],405);
    verify_csrf();
    if(empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH']??0)>0) upload_json(['ok'=>false,'error'=>'The hosting request was rejected before PHP could parse the upload. Use the resumable uploader and keep each chunk below the detected request limit.','request_id'=>upload_request_id(),'runtime'=>upload_runtime()],413);
    $action=(string)($_POST['action']??'');
    if($action==='diagnostics'){
        upload_json(['ok'=>true,'request_id'=>upload_request_id(),'runtime'=>upload_runtime(),'chunk_size'=>upload_chunk_size()]);
    }
    if($action==='init'){
        $scope=(string)($_POST['scope']??'files');
        if(!in_array($scope,['files','media','chat','support','editor','qr'],true)) upload_json(['ok'=>false,'error'=>'Invalid upload scope.'],422);if($scope==='media' && (($u['role']??'')==='admin') && is_admin_view()) upload_json(['ok'=>false,'error'=>'Media Library uploads are available from User View.'],403);
        $name=file_normalize_name((string)($_POST['name']??''));
        $size=(int)($_POST['size']??0); $mime=trim((string)($_POST['mime']??'application/octet-stream'));
        $relative=upload_safe_relative((string)($_POST['relative_path']??$name),$name);
        $context=(int)($_POST['context_id']??0); $targetFolder=upload_safe_relative((string)($_POST['folder']??''));
        if($name===''||$size<1) upload_json(['ok'=>false,'error'=>'Filename and file size are required.'],422);
        if(function_exists('file_blacklisted')&&file_blacklisted($name,$mime)) upload_json(['ok'=>false,'error'=>'This file type is blocked by policy.'],422);
        [$ok,$msg]=file_user_can_upload($uid,$size);
        if(!$ok && $scope==='files') upload_json(['ok'=>false,'error'=>$msg],413);
        if($scope==='chat'){
            $q=db()->prepare('SELECT id FROM conversations WHERE id=? AND user_id=?');$q->execute([$context,$uid]);if(!$q->fetchColumn())upload_json(['ok'=>false,'error'=>'Conversation not found.'],404);
        }
        if($scope==='support'){
            if(($u['role']??'')==='admin'){$q=db()->prepare("SELECT id FROM support_threads WHERE id=?");$q->execute([$context]);}
            else{$q=db()->prepare("SELECT id FROM support_threads WHERE id=? AND user_id=?");$q->execute([$context,$uid]);}
            if(!$q->fetchColumn())upload_json(['ok'=>false,'error'=>'Support thread not found.'],404);
        }
        if($scope==='media' && $size > max(file_plan_limits((string)$u['plan'])['file'], 5*1024*1024*1024)) upload_json(['ok'=>false,'error'=>'File exceeds the configured media limit.'],413);
        $x=upload_session_create($uid,$scope,$context,$name,$relative,$mime,$size,$targetFolder);
        upload_json(['ok'=>true,'action'=>'init','request_id'=>upload_request_id()]+$x);
    }
    $token=trim((string)($_POST['token']??''));
    if($token==='') upload_json(['ok'=>false,'error'=>'Upload token is required.'],422);
    if($action==='status'){$s=upload_session_get($uid,$token);upload_json(['ok'=>true,'action'=>'status','token'=>$token,'received'=>(int)$s['received_bytes'],'total'=>(int)$s['size_bytes'],'complete'=>((int)$s['received_bytes']===(int)$s['size_bytes']),'name'=>(string)$s['original_name'],'chunk_size'=>upload_chunk_size()]);}
    if($action==='chunk'){
        $s=upload_session_get($uid,$token); $offset=(int)($_POST['offset']??-1);
        if($offset<0) upload_json(['ok'=>false,'error'=>'Chunk offset is required.'],422);
        $up=upload_received_file('chunk');
        $received=upload_session_append($s,$offset,$up);
        upload_json(['ok'=>true,'received'=>$received,'total'=>(int)$s['size_bytes'],'complete'=>$received===(int)$s['size_bytes']]);
    }
    if($action==='complete'){
        $r=upload_session_finalize($uid,$token); $r['context_user_id']=$uid;
        if($r['scope']==='media'){
            $folder=(string)($_POST['folder']??'General');
            $r['folder']=$folder;
            $saved=upload_persist_result($r);
            upload_json(['ok'=>true,'complete'=>true,'request_id'=>upload_request_id()]+$saved);
        }
        if($r['scope']==='chat'){
            $mime=(string)$r['mime'];
            $kind=function_exists('upload_media_kind') ? upload_media_kind($mime,(string)$r['name']) : 'file';
            $db=db();
            $db->prepare('INSERT INTO chat_attachments(conversation_id,user_id,name,stored_path,storage_path,mime_type,size_bytes,kind,source_type,source_category,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
               ->execute([(int)$r['context_id'],$uid,(string)$r['name'],'',(string)$r['url'],$mime,(int)$r['size'],$kind,'ai','AI Workspace']);
            $attachmentId=(int)$db->lastInsertId();$public='/api/chat-attachment.php?id='.$attachmentId;
            $db->prepare('UPDATE chat_attachments SET stored_path=? WHERE id=? AND user_id=?')->execute([$public,$attachmentId,$uid]);
            if(function_exists('asset_catalog_register_chat'))asset_catalog_register_chat($uid,$attachmentId,(string)$r['name'],$public,$mime,(int)$r['size'],$kind);
            upload_json(['ok'=>true,'complete'=>true,'request_id'=>upload_request_id(),'token'=>$token,'name'=>$r['name'],'url'=>$public,'path'=>$public,'mime'=>$mime,'size'=>$r['size'],'context_id'=>$r['context_id'],'attachment_id'=>$attachmentId,'kind'=>$kind,'category'=>'AI Workspace']);
        }
        if($r['scope']==='support'){
            upload_json(['ok'=>true,'complete'=>true,'request_id'=>upload_request_id(),'token'=>$token,'name'=>$r['name'],'url'=>$r['url'],'path'=>$r['url'],'mime'=>$r['mime'],'size'=>$r['size'],'context_id'=>$r['context_id'],'category'=>'Support Attachments']);
        }
        upload_json(['ok'=>true,'complete'=>true,'request_id'=>upload_request_id(),'url'=>$r['url'],'path'=>$r['url'],'name'=>$r['name'],'mime'=>$r['mime'],'size'=>$r['size'],'relative_path'=>$r['relative_path']]);
    }
    if($action==='abort'){upload_session_abort($uid,$token);upload_json(['ok'=>true]);}
    upload_json(['ok'=>false,'error'=>'Unknown upload action.'],404);
} catch(Throwable $e) {
    upload_fail($e,500);
}
