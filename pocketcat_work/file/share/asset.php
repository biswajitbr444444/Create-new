<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap.php';
if(!policy_flag('file_public_share',true)){http_response_code(503);exit('Sharing is unavailable.');}
$token=preg_replace('/[^A-Za-z0-9_-]/','',(string)($_GET['token']??''));$itemKey=preg_replace('/[^a-f0-9]/i','',(string)($_GET['item']??''));$mode=(string)($_GET['mode']??'preview');
if(!preg_match('/^fs_[a-f0-9]{44}$/i',$token)||strlen($itemKey)!==64||!in_array($mode,['preview','download','text'],true)){http_response_code(404);exit('Not found.');}
$db=db();$q=$db->prepare('SELECT * FROM file_share_links WHERE token=? LIMIT 1');$q->execute([$token]);$share=$q->fetch();
if(!$share){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');header('X-Content-Type-Options: nosniff');exit('Not found.');}
if(!empty($share['revoked_at'])){http_response_code(410);exit('This share has been revoked.');}
if(!empty($share['expires_at'])&&strtotime((string)$share['expires_at'])<=time()){http_response_code(410);exit('This share has expired.');}
if(!empty($share['password_hash'])&&(int)($_SESSION['file_share_unlock_'.$share['id']]??0)<=time()-900){http_response_code(403);exit('Unlock this share before accessing files.');}
$q=$db->prepare("SELECT f.* FROM file_share_items i JOIN file_objects f ON f.id=i.file_id WHERE i.share_id=? AND f.status='clean' AND f.trashed_at IS NULL");$q->execute([(int)$share['id']]);$file=null;
foreach($q->fetchAll() as $candidate){$expected=hash_hmac('sha256',(string)$candidate['id'],$token);if(hash_equals($expected,$itemKey)){$file=$candidate;break;}}
if(!$file){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');header('X-Content-Type-Options: nosniff');exit('Not found.');}
if(!empty($file['expires_at'])&&strtotime((string)$file['expires_at'])<=time()){http_response_code(410);exit('This file has expired.');}
$mime=(string)$file['mime_type'];$previewable=str_starts_with($mime,'image/')||str_starts_with($mime,'audio/')||str_starts_with($mime,'video/')||$mime==='application/pdf'||str_starts_with($mime,'text/');
if($mode==='text'){
 if(!str_starts_with($mime,'text/')||(int)$file['size_bytes']>2097152){http_response_code(415);exit('Text preview unavailable.');}
 if(($file['storage_driver']??'local')!=='local'){http_response_code(503);exit('Text preview is unavailable for this storage provider.');}
 $path=file_local_path($file);$root=realpath(__DIR__.'/../../uploads');$real=realpath($path);if(!$real||!$root||!str_starts_with($real,$root.DIRECTORY_SEPARATOR)){http_response_code(404);exit('Not found.');}
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');echo json_encode(['content'=>substr((string)file_get_contents($real),0,2097152),'name'=>(string)$file['original_name'],'mime'=>$mime],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
if($mode==='preview'&&!$previewable){http_response_code(415);exit('Preview unavailable.');}
if($mode==='download'){
 if((int)$share['view_only']===1){http_response_code(403);exit('Downloads are disabled for this share.');}
 $db->beginTransaction();try{$u=$db->prepare('UPDATE file_share_links SET downloads=downloads+1,updated_at=NOW() WHERE id=? AND (max_downloads=0 OR downloads<max_downloads)');$u->execute([(int)$share['id']]);if($u->rowCount()!==1)throw new RuntimeException('Share download limit reached.');$db->prepare('UPDATE file_objects SET downloads=downloads+1,updated_at=NOW() WHERE id=? AND status=\'clean\'')->execute([(int)$file['id']]);$db->commit();file_record_share_event((int)$share['id'],'download',(int)$file['id']);file_record_event((int)$file['id'],'download',(int)$file['size_bytes'],false);}catch(Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(410);exit('Download limit reached or link inactive.');}
}
if(($file['storage_driver']??'local')!=='local'){http_response_code(503);exit('Secure proxy preview is not available for this storage provider yet.');}
$path=file_local_path($file);$root=realpath(__DIR__.'/../../uploads');$real=realpath($path);if(!$real||!$root||!str_starts_with($real,$root.DIRECTORY_SEPARATOR)||!is_file($real)){http_response_code(404);exit('Not found.');}
$size=(int)filesize($real);$name=(string)$file['original_name'];$safeName=preg_replace('/[^A-Za-z0-9._ -]/','_',basename($name))?:'download';$inline=$mode==='preview'&&$previewable&&!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['svg','html','htm','xhtml','xml'],true);
header('Content-Type: '.(preg_match('~^[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+$~',$mime)?$mime:'application/octet-stream'));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Referrer-Policy: no-referrer');header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$safeName.'"; filename*=UTF-8\'\''.rawurlencode(basename($name)));header('Accept-Ranges: bytes');
$start=0;$end=max(0,$size-1);$status=200;$range=(string)($_SERVER['HTTP_RANGE']??'');if($range!==''&&preg_match('/bytes=(\d*)-(\d*)/',$range,$m)&&$size>0){if($m[1]===''&&$m[2]===''){}else{$start=$m[1]===''?max(0,$size-(int)$m[2]):(int)$m[1];$end=$m[2]===''?$end:min($end,(int)$m[2]);if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}$status=206;http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}}
header('Content-Length: '.($size?($end-$start+1):0));if($_SERVER['REQUEST_METHOD']==='HEAD')exit;$fh=fopen($real,'rb');if(!$fh)exit;if($start>0)fseek($fh,$start);$remaining=$size?($end-$start+1):0;while($remaining>0&&!feof($fh)){ $chunk=fread($fh,min(1048576,$remaining));if($chunk===false||$chunk==='')break;echo $chunk;$remaining-=strlen($chunk);if(connection_aborted())break;}fclose($fh);exit;
