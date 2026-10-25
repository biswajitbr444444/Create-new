<?php
declare(strict_types=1);

/**
 * PocketCat V11.38 universal upload engine.
 * All upload requests are deliberately kept below typical shared-host request limits.
 */

function upload_request_id(): string {
    static $id = null;
    return $id ??= 'upl_'.bin2hex(random_bytes(6));
}
function upload_log(Throwable $e, string $scope='upload'): void {
    error_log('[PocketCat '.$scope.' '.upload_request_id().'] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
}
function upload_json(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function upload_fail(Throwable $e, int $status=500, string $public='Upload service failed.'): never {
    upload_log($e);
    upload_json(['ok'=>false,'error'=>$public,'request_id'=>upload_request_id()], $status);
}
function upload_app_root(): string {
    // The source tree is the stable anchor across cPanel, InfinityFree, Cloudways,
    // public_html/htdocs layouts and domain subdirectories; DOCUMENT_ROOT is not.
    $root = realpath(dirname(__DIR__));
    if ($root === false || !is_dir($root)) throw new RuntimeException('Could not resolve the application root from the upload engine.');
    return rtrim($root, DIRECTORY_SEPARATOR);
}
function upload_storage_path(string $relative): string {
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '' || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative)) throw new InvalidArgumentException('Storage path must be relative to the application root.');
    $parts = explode('/', $relative);
    if (in_array('..', $parts, true) || in_array('.', $parts, true) || in_array('', $parts, true)) throw new InvalidArgumentException('Storage path contains an unsafe segment.');
    return upload_app_root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function upload_prepare_directories(): void {
    $roots=[upload_storage_path('storage'),upload_storage_path('storage/tmp'),upload_storage_path('uploads'),upload_storage_path('uploads/.chunks'),upload_storage_path('uploads/files'),upload_storage_path('uploads/media'),upload_storage_path('uploads/support'),upload_storage_path('uploads/qr')];
    foreach($roots as $dir){if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Could not initialize writable upload directory: '.$dir);}
    if(!is_writable(upload_storage_path('uploads')))throw new RuntimeException('The uploads directory is not writable.');
    if(!is_writable(upload_storage_path('storage/tmp')))throw new RuntimeException('The application temporary directory is not writable.');
}
function upload_runtime(): array {
    $tmp=(string)ini_get('upload_tmp_dir');
    if($tmp==='') $tmp=sys_get_temp_dir();
    return [
        'upload_max_filesize'=>(string)ini_get('upload_max_filesize'),
        'post_max_size'=>(string)ini_get('post_max_size'),
        'max_file_uploads'=>(int)ini_get('max_file_uploads'),
        'max_execution_time'=>(int)ini_get('max_execution_time'),
        'memory_limit'=>(string)ini_get('memory_limit'),
        'upload_tmp_dir'=>$tmp,
        'upload_tmp_writable'=>is_dir($tmp) && is_writable($tmp),
        'temp_dir'=>sys_get_temp_dir(),
        'temp_writable'=>is_writable(sys_get_temp_dir()),
    ];
}
function upload_parse_ini_bytes(string $v): int {
    $v=trim(strtolower($v));
    if($v==='' || $v==='-1') return PHP_INT_MAX;
    if(!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?b?)?$/i',$v,$m)) return 0;
    $n=(float)$m[1]; $u=$m[2]??'';
    $mult=match($u){'t','tb'=>1099511627776,'g','gb'=>1073741824,'m','mb'=>1048576,'k','kb'=>1024,default=>1};
    return (int)min(PHP_INT_MAX,$n*$mult);
}
function upload_request_limit_bytes(): int {
    $a=upload_parse_ini_bytes((string)ini_get('upload_max_filesize'));
    $b=upload_parse_ini_bytes((string)ini_get('post_max_size'));
    $vals=array_values(array_filter([$a,$b],fn($x)=>$x>0 && $x<PHP_INT_MAX));
    if(!$vals) return 8*1024*1024;
    // The effective PHP request limit is the LOWER of upload_max_filesize and post_max_size.
    // Using max() here can make the browser send a chunk larger than upload_max_filesize,
    // which PHP rejects before the upload handler can read $_FILES.
    return max(131072,min($vals));
}
function upload_chunk_size(): int {
    // Keep multipart overhead comfortably below the effective shared-host request limit.
    // 256 KiB also avoids failures on hosts configured with a 1 MiB upload_max_filesize.
    $limit=upload_request_limit_bytes();
    if($limit<=393216) return max(131072,intdiv($limit*2,3));
    return min(262144,$limit-65536);
}
function upload_area_root(string $area,int $uid): string {
    $area=preg_replace('/[^a-z0-9_-]/i','',strtolower($area))?:'generic';
    $root=upload_storage_path('uploads/'.$area.'/'.$uid);
    if(!is_dir($root) && !@mkdir($root,0750,true) && !is_dir($root)){
        throw new RuntimeException('Upload storage directory is not writable.');
    }
    if(!is_writable($root)) throw new RuntimeException('Upload storage directory is not writable.');
    return $root;
}
function upload_chunk_root(int $uid): string {
    $root=upload_storage_path('uploads/.chunks/'.$uid);
    if(!is_dir($root) && !@mkdir($root,0750,true) && !is_dir($root)){
        throw new RuntimeException('Temporary upload directory is not writable.');
    }
    if(!is_writable($root)) throw new RuntimeException('Temporary upload directory is not writable.');
    return $root;
}
function upload_safe_relative(string $path,string $fallback=''): string {
    $path=str_replace('\\','/',trim($path));
    $parts=[];
    foreach(explode('/',$path) as $part){
        $part=trim($part);
        if($part===''||$part==='.'||$part==='..') continue;
        $part=preg_replace('/[^A-Za-z0-9._()\- ]+/u','_',basename($part))?:'_';
        $parts[]=substr($part,0,180);
    }
    if(!$parts && $fallback!=='') return file_normalize_name($fallback);
    return implode('/',$parts);
}
function upload_extension(string $name): string {
    $ext=pathinfo($name,PATHINFO_EXTENSION);
    return $ext!==''?'.'.preg_replace('/[^A-Za-z0-9]+/','',$ext):'';
}
function upload_received_file(string $field): array {
    if(!isset($_FILES[$field]) || !is_array($_FILES[$field])) return ['error'=>UPLOAD_ERR_NO_FILE,'tmp'=>'','size'=>0,'name'=>'','mime'=>'application/octet-stream'];
    $f=$_FILES[$field];
    return ['error'=>(int)($f['error']??UPLOAD_ERR_NO_FILE),'tmp'=>(string)($f['tmp_name']??''),'size'=>(int)($f['size']??0),'name'=>(string)($f['name']??''),'mime'=>(string)($f['type']??'application/octet-stream')];
}
function upload_move_received(array $up,string $dest,int $expectedSize=0): void {
    if(($up['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException(file_upload_error_message((int)$up['error']));
    if(($up['tmp']??'')==='' || !is_uploaded_file((string)$up['tmp'])) throw new RuntimeException('The server did not receive a valid uploaded file.');
    $size=(int)($up['size']??0);
    if($expectedSize>0 && $size!==$expectedSize) throw new RuntimeException('Uploaded file size does not match the expected size.');
    $dir=dirname($dest);
    if(!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Could not create the destination directory.');
    if(!@move_uploaded_file((string)$up['tmp'],$dest) && !@copy((string)$up['tmp'],$dest)) throw new RuntimeException('The server could not write the uploaded file.');
    if(!is_file($dest) || ($expectedSize>0 && (int)filesize($dest)!==$expectedSize)){@unlink($dest);throw new RuntimeException('The server wrote an incomplete file.');}
}
function upload_session_token(): string { return 'us_'.bin2hex(random_bytes(28)); }
function upload_unique_session_name(int $uid,string $scope,int $contextId,string $name): string {
    $name=file_normalize_name($name);
    if($name==='') return 'file';
    $stem=pathinfo($name,PATHINFO_FILENAME);
    $ext=pathinfo($name,PATHINFO_EXTENSION);
    $suffix=$ext!==''?'.'.$ext:'';
    $candidate=$name;
    $n=0;
    $q=db()->prepare("SELECT 1 FROM upload_sessions WHERE user_id=? AND scope=? AND context_id=? AND original_name=? AND status IN ('uploading','completed') LIMIT 1");
    while(true){
        $q->execute([$uid,$scope,$contextId,$candidate]);
        if(!$q->fetchColumn()) return $candidate;
        $n++;
        $candidate=substr($stem,0,max(1,220-strlen($suffix)-strlen((string)$n)-3)).' ('.$n.')'.$suffix;
    }
}
function upload_session_create(int $uid,string $scope,int $contextId,string $name,string $relative,string $mime,int $size,string $targetFolder=''): array {
    $db=db(); $lock=null;
    try {
        // Serialize name allocation for bulk uploads on MySQL shared hosting.
        $lock='pocketcat_upload_session_name_'.$uid.'_'.preg_replace('/[^a-z0-9_-]/i','',$scope);
        $l=$db->prepare('SELECT GET_LOCK(?,5)'); $l->execute([$lock]);
        if((int)$l->fetchColumn()!==1) $lock=null;
        $name=upload_unique_session_name($uid,$scope,$contextId,$name);
        $relative=upload_safe_relative($relative,$name);
        if($relative!==''){$dir=trim(str_replace('\\','/',dirname($relative)),'. /');$relative=$dir!==''?$dir.'/'.basename($name):$name;}
        $token=upload_session_token();
        $root=upload_chunk_root($uid); $part=$root.'/'.$token.'.part';
        if(@file_put_contents($part,'',LOCK_EX)===false) throw new RuntimeException('Could not initialize the temporary upload file.');
        $sourceType=match($scope){'media'=>'media','chat'=>'ai','support'=>'support','editor'=>'media',default=>'storage'};
        $sourceCategory=asset_category($sourceType);
        $db->prepare("INSERT INTO upload_sessions(user_id,token,scope,source_type,source_category,context_id,original_name,relative_path,target_folder,mime_type,size_bytes,received_bytes,temp_path,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?, 'uploading',NOW(),NOW())")
           ->execute([$uid,$token,$scope,$sourceType,$sourceCategory,$contextId,$name,$relative,$targetFolder,$mime,$size,0,$part]);
        return ['token'=>$token,'chunk_size'=>upload_chunk_size(),'source_type'=>$sourceType,'source_category'=>$sourceCategory,'name'=>$name,'relative_path'=>$relative];
    } finally {
        if($lock!==null){try{$l=$db->prepare('SELECT RELEASE_LOCK(?)');$l->execute([$lock]);}catch(Throwable $ignored){}}
    }
}
function upload_session_get(int $uid,string $token): array {
    $q=db()->prepare('SELECT * FROM upload_sessions WHERE user_id=? AND token=? LIMIT 1');$q->execute([$uid,$token]);$s=$q->fetch();
    if(!$s) throw new RuntimeException('Upload session not found.');
    if($s['status']!=='uploading') throw new RuntimeException('Upload session is no longer active.');
    return $s;
}
function upload_session_append(array $s,int $offset,array $up): int {
    if($up['error']!==UPLOAD_ERR_OK) throw new RuntimeException(file_upload_error_message($up['error']));
    if($up['tmp']==='' || !is_uploaded_file($up['tmp'])) throw new RuntimeException('The server did not receive a valid upload chunk.');
    $chunk=(int)$up['size']; if($chunk<1 || $chunk>upload_chunk_size()) throw new RuntimeException('Invalid upload chunk size.');
    $expected=(int)$s['received_bytes']; if($offset!==$expected) throw new RuntimeException('Upload offset mismatch. Expected '.$expected.' bytes.',);
    if($expected+$chunk>(int)$s['size_bytes']) throw new RuntimeException('Upload exceeds the declared file size.');
    $fp=@fopen((string)$s['temp_path'],'ab'); if(!$fp) throw new RuntimeException('Could not open the temporary upload file.');
    if(!flock($fp,LOCK_EX)){fclose($fp);throw new RuntimeException('Could not lock the temporary upload file.');}
    $in=@fopen((string)$up['tmp'],'rb'); if(!$in){flock($fp,LOCK_UN);fclose($fp);throw new RuntimeException('Could not read the upload chunk.');}
    $written=0;
    while(!feof($in)){
        $buf=fread($in,1048576); if($buf===false){fclose($in);flock($fp,LOCK_UN);fclose($fp);throw new RuntimeException('Could not read the upload chunk.');}
        if($buf==='') continue;
        $n=fwrite($fp,$buf); if($n===false || $n!==strlen($buf)){fclose($in);flock($fp,LOCK_UN);fclose($fp);throw new RuntimeException('Could not write the upload chunk.');}
        $written+=$n;
    }
    fclose($in);flock($fp,LOCK_UN);fclose($fp);
    $new=$expected+$written;
    db()->prepare('UPDATE upload_sessions SET received_bytes=?,updated_at=NOW() WHERE id=? AND user_id=? AND status=\'uploading\'')->execute([$new,(int)$s['id'],$uid=(int)$s['user_id']]);
    return $new;
}
function upload_session_finalize(int $uid,string $token): array {
    $s=upload_session_get($uid,$token); $size=(int)$s['size_bytes'];
    if((int)$s['received_bytes']!==$size) throw new RuntimeException('Upload is incomplete. Received '.$s['received_bytes'].' of '.$size.' bytes.');
    if(!is_file($s['temp_path']) || (int)filesize($s['temp_path'])!==$size) throw new RuntimeException('Temporary upload data is incomplete.');
    $scope=(string)$s['scope']; $name=file_normalize_name((string)$s['original_name']); $rel=upload_safe_relative((string)$s['relative_path'],$name);
    $area=match($scope){'media'=>'media','chat'=>'media','support'=>'support','editor'=>'media','qr'=>'qr',default=>'files'};
    $root=upload_area_root($area,$uid); $sub=($scope==='chat'?'chat/':($scope==='editor'?'editor/':($scope==='qr'?'qr/':''))); if($scope==='media'){ $target=upload_safe_relative((string)($s['target_folder']??'')); $relativeDir=dirname($rel); if($relativeDir!=='.'&&$relativeDir!=='') $target=trim($target.'/'.$relativeDir,'/'); if($target!=='') $sub=trim($target,'/').'/'; }
    if($sub!==''){$destDir=$root.'/'.$sub;if(!is_dir($destDir)&&!@mkdir($destDir,0750,true)&&!is_dir($destDir))throw new RuntimeException('Could not create upload destination.');}else $destDir=$root;
    $ext=upload_extension($name); $stored=bin2hex(random_bytes(18)).$ext; $dest=$destDir.'/'.$stored;
    if(!@rename((string)$s['temp_path'],$dest) && !@copy((string)$s['temp_path'],$dest)){throw new RuntimeException('Could not finalize the uploaded file.');}
    if(!is_file($dest) || (int)filesize($dest)!==$size){@unlink($dest);throw new RuntimeException('Finalized file is incomplete.');}
    $detectedMime=function_exists('mime_content_type')?(mime_content_type($dest)?:''):'';
    $finalMime=$detectedMime!==''?$detectedMime:(string)$s['mime_type'];
    if(function_exists('file_blacklisted') && file_blacklisted($name,$finalMime)){@unlink($dest);db()->prepare("UPDATE upload_sessions SET status='failed',updated_at=NOW() WHERE id=? AND user_id=?")->execute([(int)$s['id'],$uid]);throw new RuntimeException('This file type is blocked by policy.');}
    $url='/uploads/'.$area.'/'.$uid.'/'.($sub?trim($sub,'/').'/':'').$stored;
    if($scope==='support') $url='/support/file/?thread='.(int)$s['context_id'].'&file='.rawurlencode($uid.'/'.$stored);
    db()->prepare("UPDATE upload_sessions SET status='completed',final_path=?,public_path=?,updated_at=NOW() WHERE id=? AND user_id=?")->execute([$dest,$url,(int)$s['id'],$uid]);
    return ['session_id'=>(int)$s['id'],'token'=>$token,'name'=>$name,'relative_path'=>$rel,'mime'=>$finalMime,'size'=>$size,'path'=>$dest,'url'=>$url,'scope'=>$scope,'context_id'=>(int)$s['context_id']];
}
function upload_session_abort(int $uid,string $token): void {
    $q=db()->prepare('SELECT temp_path FROM upload_sessions WHERE user_id=? AND token=? LIMIT 1');$q->execute([$uid,$token]);$s=$q->fetch();
    if($s && !empty($s['temp_path'])) @unlink((string)$s['temp_path']);
    db()->prepare("UPDATE upload_sessions SET status='aborted',updated_at=NOW() WHERE user_id=? AND token=?")->execute([$uid,$token]);
}
function upload_media_kind(string $mime,string $name): string { $m=strtolower($mime);$n=strtolower($name);if(str_starts_with($m,'image/'))return'image';if(str_starts_with($m,'audio/'))return'audio';if(str_starts_with($m,'video/'))return'video';if($m==='application/zip'||str_ends_with($n,'.zip'))return'archive';return'file'; }
function upload_persist_result(array $r): array {
    $uid=(int)$r['context_user_id'];
    $scope=(string)$r['scope']; $name=(string)$r['name']; $mime=(string)$r['mime']; $size=(int)$r['size'];
    if($scope==='media'){
        $folder=upload_safe_relative((string)$r['folder'],'General') ?: 'General';
        $q=db()->prepare('SELECT 1 FROM media_assets WHERE user_id=? AND folder_name=? LIMIT 1');$q->execute([$uid,$folder]);
        if(!$q->fetchColumn()) db()->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_url,mime_type,size_bytes,kind,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,NOW(),NOW())')->execute([$uid,'.folder-marker',$folder,'url','about:blank','application/x-folder-marker',0,'file']);
        $kind=upload_media_kind((string)$mime,$name);$w=$h=null;if($kind==='image'){ $i=@getimagesize((string)$r['path']);$w=$i[0]??null;$h=$i[1]??null; }
        // Register chunked Media Library uploads in the same canonical File Storage catalog as regular uploads.
        // Public URLs then resolve through the opaque file token rather than exposing an upload path.
        $objectKey=ltrim(substr((string)$r['url'],strlen('/uploads/')),'/');
        if(!str_starts_with($objectKey,'media/'.$uid.'/')) throw new RuntimeException('Invalid finalized media object path.');
        $token=bin2hex(random_bytes(16));$etag=hash_file('sha256',(string)$r['path'])?:null;$pdo=db();$pdo->beginTransaction();
        $pdo->prepare("INSERT INTO file_objects(user_id,public_token,object_key,original_name,relative_path,mime_type,size_bytes,kind,source_type,source_category,storage_driver,status,scan_status,etag,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$uid,$token,$objectKey,$name,(string)($r['relative_path']??$name),$mime,$size,$kind,'media','Media Assets','local','clean','clean',$etag]);
        $fileId=(int)$pdo->lastInsertId();$assetUrl=file_asset_url(['id'=>$fileId,'public_token'=>$token,'object_key'=>$objectKey,'storage_driver'=>'local','mime_type'=>$mime]);
        $pdo->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_category,source_ref_id,canonical_file_id,source_url,storage_path,mime_type,size_bytes,kind,width,height,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())')->execute([$uid,$name,$folder,'upload','Media Assets',0,$fileId,$assetUrl,$objectKey,$mime,$size,$kind,$w,$h]);
        $mediaId=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE media_assets SET source_ref_id=? WHERE id=? AND user_id=?')->execute([$mediaId,$mediaId,$uid]);$pdo->commit();
        if(function_exists('file_record_version'))file_record_version($fileId,$objectKey,$size,(string)$etag);
        return ['id'=>$mediaId,'file_id'=>$fileId,'url'=>$assetUrl,'name'=>$name,'folder'=>$folder,'kind'=>$kind,'category'=>'Media Assets'];
    }
    return ['url'=>$r['url'],'name'=>$name,'size'=>$size];
}
