<?php
declare(strict_types=1);

/**
 * Keep the File Storage / Media Library pages boot-safe when an older
 * installation has not yet run every V12-V14 ALTER statement.  The
 * installer schema creates the base tables; this compatibility pass only
 * adds the small bridge fields/tables required by the current UI.
 */
function ensure_file_workspace_schema(): void {
    static $done=false;
    if($done)return;
    $done=true;
    try {
        $db=db();
        $hasTable=function(string $table) use ($db): bool {
            $q=$db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
            $q->execute([$table]); return (bool)$q->fetchColumn();
        };
        $hasColumn=function(string $table,string $column) use ($db): bool {
            $q=$db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
            $q->execute([$table,$column]); return (bool)$q->fetchColumn();
        };
        $addColumn=function(string $table,string $column,string $definition) use ($hasTable,$hasColumn,$db): void {
            if($hasTable($table) && !$hasColumn($table,$column)) $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        };

        $addColumn('file_objects','relative_path','VARCHAR(600) NULL AFTER original_name');
        $addColumn('file_objects','trashed_at','DATETIME NULL AFTER status');
        $addColumn('file_objects','trashed_by','BIGINT UNSIGNED NULL AFTER trashed_at');
        $addColumn('file_objects','view_only','TINYINT(1) NOT NULL DEFAULT 0 AFTER burn_after_reading');
        $addColumn('file_objects','source_type',"VARCHAR(40) NOT NULL DEFAULT 'storage' AFTER kind");
        $addColumn('file_objects','source_category',"VARCHAR(80) NOT NULL DEFAULT 'Shared Files' AFTER source_type");

        $addColumn('media_assets','source_category',"VARCHAR(80) NOT NULL DEFAULT 'Media Assets' AFTER source_type");
        $addColumn('media_assets','source_ref_id','BIGINT UNSIGNED NULL AFTER source_category');
        $addColumn('media_assets','canonical_file_id','BIGINT UNSIGNED NULL AFTER source_ref_id');

        if(!$hasTable('file_share_links')) {
            $db->exec("CREATE TABLE file_share_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,link_id BIGINT UNSIGNED NULL,token CHAR(48) NOT NULL UNIQUE,title VARCHAR(220) NOT NULL,password_hash VARCHAR(255) NULL,expires_at DATETIME NULL,revoked_at DATETIME NULL,max_downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,view_only TINYINT(1) NOT NULL DEFAULT 0,theme_json TEXT NULL,og_title VARCHAR(220) NULL,og_description TEXT NULL,og_image_url TEXT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),INDEX(expires_at),INDEX(link_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $addColumn('file_share_links','link_id','BIGINT UNSIGNED NULL AFTER id');
            $addColumn('file_share_links','revoked_at','DATETIME NULL AFTER expires_at');
            $addColumn('file_share_links','theme_json','TEXT NULL AFTER view_only');
            $addColumn('file_share_links','og_title','VARCHAR(220) NULL AFTER theme_json');
            $addColumn('file_share_links','og_description','TEXT NULL AFTER og_title');
            $addColumn('file_share_links','og_image_url','TEXT NULL AFTER og_description');
        }
        if(!$hasTable('file_share_items')) {
            $db->exec("CREATE TABLE file_share_items(share_id BIGINT UNSIGNED NOT NULL,file_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY(share_id,file_id),CONSTRAINT fk_file_share_item_share FOREIGN KEY(share_id) REFERENCES file_share_links(id) ON DELETE CASCADE,CONSTRAINT fk_file_share_item_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if(!$hasTable('file_share_events')) {
            $db->exec("CREATE TABLE file_share_events(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,share_id BIGINT UNSIGNED NOT NULL,file_id BIGINT UNSIGNED NULL,event_type VARCHAR(40) NOT NULL,visitor_hash CHAR(64) NULL,ip_hash CHAR(64) NULL,referrer VARCHAR(500) NULL,user_agent VARCHAR(1000) NULL,occurred_at DATETIME NOT NULL,INDEX idx_share_event(share_id,occurred_at),INDEX idx_share_event_type(share_id,event_type,occurred_at),CONSTRAINT fk_file_share_event_share FOREIGN KEY(share_id) REFERENCES file_share_links(id) ON DELETE CASCADE,CONSTRAINT fk_file_share_event_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch(Throwable $e) {
        error_log('[PocketCat file workspace schema] '.$e->getMessage());
    }
}

function file_setting(string $k, ?string $d=null): ?string { return setting('file_'.$k,$d); }
function file_ip_blocked(): bool { try{$h=hash_hmac('sha256',client_ip(),app_secret());$s=db()->prepare('SELECT 1 FROM file_abuse_blocks WHERE ip_hash=? AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');$s->execute([$h]);return (bool)$s->fetchColumn();}catch(Throwable $e){return false;} }

function file_bool_setting(string $k, bool $d=false): bool { return (int)(file_setting($k,$d?'1':'0')??($d?'1':'0'))===1; }
function storage_parse_size(string $v,int $fallback=1):int{$v=trim(str_replace(',','',$v));if($v==='')return $fallback;$n=(float)$v;$u=strtolower(substr($v,-2));if($u==='tb')$n*=1099511627776;elseif($u==='gb')$n*=1073741824;elseif($u==='mb')$n*=1048576;elseif($u==='kb')$n*=1024;return max(1,(int)$n);}
function storage_human(int $b):string{$units=['B'=>1,'KB'=>1024,'MB'=>1048576,'GB'=>1073741824,'TB'=>1099511627776];$x=$b;$unit='B';foreach($units as $k=>$n){if($b>=$n){$x=$b/$n;$unit=$k;}}return rtrim(rtrim(number_format($x,2,'.',''),'0'),'.').' '.$unit;}
function file_storage_config(): array {
    $driver=(string)file_setting('storage_driver','local'); if(!in_array($driver,['local','s3','google_drive','onedrive','dropbox'],true))$driver='local';
    return [
        'driver'=>$driver,
        'endpoint'=>rtrim((string)file_setting('storage_endpoint',''),'/'),
        'region'=>file_setting('storage_region','us-east-1'),
        'bucket'=>file_setting('storage_bucket',''),
        'access_key'=>secret_setting('storage_access_key',''),
        'secret_key'=>secret_setting('storage_secret_key',''),
        'public_base'=>rtrim((string)file_setting('storage_public_base',''),'/'),
        'max_part_mb'=>(int)file_setting('storage_max_part_mb','100'),
    ];
}
function file_plan_limits(string $plan): array {
    if(function_exists('policy_testing_mode') && policy_testing_mode()) return ['file'=>PHP_INT_MAX,'storage'=>PHP_INT_MAX];
    $p=policy_plan($plan);
    $cfg=['file'=>(int)($p['file_max_bytes']??100*1024*1024),'storage'=>(int)($p['file_storage_bytes']??1*1024*1024*1024)];
    $cfg['file']=(int)file_setting('quota_'.$plan.'_file',(string)$cfg['file']);
    $cfg['storage']=(int)file_setting('quota_'.$plan.'_storage',(string)$cfg['storage']);
    return $cfg;
}
function file_user_used_bytes(int $uid): int { $s=db()->prepare("SELECT COALESCE(SUM(size_bytes),0) FROM file_objects WHERE user_id=? AND status NOT IN ('deleted','expired','quarantined')");$s->execute([$uid]);return (int)$s->fetchColumn(); }
function file_emit_user(int $uid,string $event,array $extra=[]): void { try{$q=db()->prepare('SELECT id,url,secret FROM file_webhooks WHERE user_id=? AND active=1');$q->execute([$uid]);$payload=['event'=>$event,'user_id'=>$uid,'timestamp'=>gmdate('c')]+$extra;$json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);foreach($q->fetchAll() as $h){if(!webhook_target_is_safe((string)$h['url'])){error_log('[PocketCat file webhook] blocked unsafe target for webhook '.(int)$h['id']);continue;}$sig=hash_hmac('sha256',$json,(string)$h['secret']);$ch=curl_init($h['url']);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-PocketCat-Signature: '.$sig],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>500,CURLOPT_TIMEOUT_MS=>2000,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]);curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);db()->prepare('INSERT INTO file_webhook_deliveries(webhook_id,event_name,status_code,created_at) VALUES(?,?,?,NOW())')->execute([(int)$h['id'],$event,$code]);}}catch(Throwable $e){} }
function file_user_can_upload(int $uid,int $size): array { $plan='free';try{$q=db()->prepare("SELECT plan FROM users WHERE id=? AND status='active'");$q->execute([$uid]);$plan=(string)($q->fetchColumn()?:'free');}catch(Throwable $e){}$lim=file_plan_limits($plan);$used=file_user_used_bytes($uid);if($size>$lim['file']){file_emit_user($uid,'quota.exceeded',['reason'=>'per_file','size'=>$size,'limit'=>$lim['file']]);return [false,'File exceeds your per-file limit.'];}if($used+$size>$lim['storage']){file_emit_user($uid,'quota.exceeded',['reason'=>'storage','requested'=>$size,'used'=>$used,'limit'=>$lim['storage']]);return [false,'Storage quota exceeded.'];}return [true,'OK']; }
function file_slug(int $len=12): string { $a='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; do{$s='';for($i=0;$i<$len;$i++)$s.=$a[random_int(0,strlen($a)-1)];$q=db()->prepare('SELECT 1 FROM file_objects WHERE public_token=? LIMIT 1');$q->execute([$s]);}while($q->fetchColumn());return $s; }
function file_normalize_name(string $n): string { $n=preg_replace('/[^A-Za-z0-9._()\- ]+/u','_',basename($n))?:'file';return substr($n,0,220); }
function file_unique_upload_lock(int $uid): ?string {
    try {
        $key='pocketcat_file_name_'.$uid;
        $q=db()->prepare('SELECT GET_LOCK(?,5)');
        $q->execute([$key]);
        return ((int)$q->fetchColumn()===1) ? $key : null;
    } catch(Throwable $e) {
        return null;
    }
}
function file_unique_upload_unlock(?string $key): void {
    if($key===null) return;
    try { $q=db()->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$key]); } catch(Throwable $e) {}
}
function file_unique_upload_name(int $uid,string $name): string {
    $name=file_normalize_name($name);
    if($name==='') return 'file';
    $stem=pathinfo($name,PATHINFO_FILENAME);
    $ext=pathinfo($name,PATHINFO_EXTENSION);
    $suffix=$ext!==''?'.'.$ext:'';
    $candidate=$name;
    $n=0;
    $q=db()->prepare("SELECT 1 FROM file_objects WHERE user_id=? AND original_name=? AND status<>'deleted' LIMIT 1");
    while(true){
        $q->execute([$uid,$candidate]);
        if(!$q->fetchColumn()) return $candidate;
        $n++;
        $candidate=substr($stem,0,max(1,220-strlen($suffix)-strlen((string)$n)-3)).' ('.$n.')'.$suffix;
    }
}
function file_ext(string $n): string { return strtolower(pathinfo($n,PATHINFO_EXTENSION)); }
function file_blacklisted(string $name,string $mime): bool { $exts=json_decode((string)file_setting('blacklist_extensions','["exe","bat","cmd","sh","bash","vbs","vbe","js","jar","msi","scr","com","ps1"]'),true)?:[];$mimes=json_decode((string)file_setting('blacklist_mimes','["application/x-msdownload","application/x-dosexec"]'),true)?:[];return in_array(file_ext($name),array_map('strtolower',$exts),true)||in_array(strtolower($mime),array_map('strtolower',$mimes),true); }
function file_kind(string $mime): string { if(str_starts_with($mime,'image/'))return 'image';if(str_starts_with($mime,'video/'))return 'video';if(str_starts_with($mime,'audio/'))return 'audio';if(in_array($mime,['application/pdf','text/plain','text/html','application/json','text/css','text/javascript','application/javascript'],true)||str_starts_with($mime,'text/'))return 'document';if(in_array($mime,['application/zip','application/x-rar-compressed','application/vnd.rar','application/x-7z-compressed'],true))return 'archive';return 'file'; }
function file_safe_key(string $token,string $name): string { return 'users/'.(int)(current_user()['id']??0).'/'.$token.'/'.bin2hex(random_bytes(8)).'-'.preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name)); }
function s3_cfg_ok(): bool { $c=file_storage_config();return ($c['driver']??'s3')==='s3'&&$c['endpoint']!==''&&$c['bucket']!==''&&$c['access_key']!==''&&$c['secret_key']!==''; }
function file_local_root(): string { $dir=__DIR__.'/../uploads/files'; if(!is_dir($dir))@mkdir($dir,0750,true); return $dir; }
function file_local_max_bytes(): int {
    $parse=function($v){$v=trim((string)$v);if($v==='')return 0;$n=(float)$v;$u=strtolower(substr($v,-1));if($u==='g')$n*=1024*1024*1024;elseif($u==='m')$n*=1024*1024;elseif($u==='k')$n*=1024;return (int)$n;};
    $a=$parse(ini_get('upload_max_filesize'));$b=$parse(ini_get('post_max_size'));$vals=array_filter([$a,$b]);return $vals?max(1024*1024,min($vals)-262144):8*1024*1024;
}
function file_storage_driver(?int $uid=null): string { $c=file_storage_config(); $driver=(string)$c['driver']; if($driver==='s3')return s3_cfg_ok()?'s3':'local'; if(in_array($driver,['google_drive','onedrive','dropbox'],true))return function_exists('cloud_connection_status')&&cloud_connection_status($driver)['connected']?$driver:'local'; return 'local'; }
function file_storage_folder(?int $uid=null): string { return trim(cloud_storage_folder())||'PocketCat'; }
function file_local_path(array $f): string {
    $key=ltrim((string)$f['object_key'],'/');
    if(str_starts_with($key,'media/')) return __DIR__.'/../uploads/'.$key;
    return file_local_root().'/'.$key;
}
function file_local_url(array $f,bool $download=false): string { return rtrim((string)app_config()['app_url'],'/').'/file/download/?id='.(int)$f['id'].'&token='.rawurlencode((string)$f['public_token']).($download?'&download=1':''); }
function s3_sign(string $method,string $uri,array $query,array $headers,string $payloadHash): array {
    $c=file_storage_config();$host=parse_url($c['endpoint'],PHP_URL_HOST);$amz=date('Ymd\THis\Z');$date=substr($amz,0,8);$headers2=[];foreach($headers as $k=>$v)$headers2[strtolower($k)]=trim((string)$v);$headers2['host']=$host;ksort($headers2);$signed=[];$canonicalHeaders='';foreach($headers2 as $k=>$v){$signed[]=$k;$canonicalHeaders.=$k.':'.preg_replace('/\s+/',' ',(string)$v)."\n";}$canonicalQuery=[];foreach($query as $k=>$v){$canonicalQuery[rawurlencode((string)$k)]=rawurlencode((string)$v);}ksort($canonicalQuery);$cq=[];foreach($canonicalQuery as $k=>$v)$cq[]=$k.'='.$v;$canonicalQueryString=implode('&',$cq);$canonicalUri='/' . ltrim($uri,'/');$canonicalRequest=implode("\n",[$method,$canonicalUri,$canonicalQueryString,$canonicalHeaders,implode(';',$signed),$payloadHash]);$scope=$date.'/'.$c['region'].'/s3/aws4_request';$stringToSign="AWS4-HMAC-SHA256\n{$amz}\n{$scope}\n".hash('sha256',$canonicalRequest);$kDate=hash_hmac('sha256',$date,'AWS4'.$c['secret_key'],true);$kRegion=hash_hmac('sha256',$c['region'],$kDate,true);$kService=hash_hmac('sha256','s3',$kRegion,true);$kSigning=hash_hmac('sha256','aws4_request',$kService,true);$signature=hash_hmac('sha256',$stringToSign,$kSigning);$headers2['x-amz-date']=$amz;$headers2['x-amz-content-sha256']=$payloadHash;$headers2['authorization']='AWS4-HMAC-SHA256 Credential='.$c['access_key'].'/'.$scope.', SignedHeaders='.implode(';',$signed).', Signature='.$signature;return $headers2; }
function s3_presigned_url(string $method,string $key,int $expires=900,string $contentType='',array $extraQuery=[]): string {
    $c=file_storage_config();$host=parse_url($c['endpoint'],PHP_URL_HOST);if(!$host)throw new RuntimeException('Storage endpoint is invalid.');$now=time();$amz=gmdate('Ymd\\THis\\Z',$now);$date=gmdate('Ymd',$now);$scope=$date.'/'.$c['region'].'/s3/aws4_request';
    $path='/'.trim($c['bucket'],'/').'/'.$key;
    $signedHeaders=$contentType?'host;content-type':'host';
    $query=['X-Amz-Algorithm'=>'AWS4-HMAC-SHA256','X-Amz-Credential'=>$c['access_key'].'/'.$scope,'X-Amz-Date'=>$amz,'X-Amz-Expires'=>max(60,min(604800,$expires)),'X-Amz-SignedHeaders'=>$signedHeaders];
    foreach($extraQuery as $k=>$v)$query[$k]=(string)$v;
    ksort($query,SORT_STRING);$qs=[];foreach($query as $k=>$v)$qs[]=rawurlencode((string)$k).'='.rawurlencode((string)$v);$canonicalQuery=implode('&',$qs);
    $canonicalHeaders='host:'.$host."\n".($contentType?'content-type:'.trim($contentType)."\n":'');
    $canonical=implode("\n",[$method,$path,$canonicalQuery,$canonicalHeaders,$signedHeaders,'UNSIGNED-PAYLOAD']);$sts="AWS4-HMAC-SHA256\n{$amz}\n{$scope}\n".hash('sha256',$canonical);
    $kd=hash_hmac('sha256',$date,'AWS4'.$c['secret_key'],true);$kr=hash_hmac('sha256',$c['region'],$kd,true);$ks=hash_hmac('sha256','s3',$kr,true);$ks=hash_hmac('sha256','aws4_request',$ks,true);$query['X-Amz-Signature']=hash_hmac('sha256',$sts,$ks);
    $out=[];foreach($query as $k=>$v)$out[]=rawurlencode((string)$k).'='.rawurlencode((string)$v);return rtrim($c['endpoint'],'/').'/'.rawurlencode($c['bucket']).'/'.$key.'?'.implode('&',$out);
}
function s3_request(string $method,string $key,string $body='',array $extraQuery=[]): array { $c=file_storage_config();$uri='/'.trim($c['bucket'],'/').'/'.str_replace('%2F','/',rawurlencode($key));$payloadHash=hash('sha256',$body);$headers=s3_sign($method,$uri,$extraQuery,['content-type'=>'application/xml'],$payloadHash);$url=rtrim($c['endpoint'],'/').'/'.rawurlencode($c['bucket']).'/'.$key;if($extraQuery)$url.='?'.http_build_query($extraQuery);$send=[];foreach($headers as $k=>$v)if(in_array($k,['host','authorization','x-amz-date','x-amz-content-sha256','content-type'],true))$send[]=ucwords(str_replace('-',' ',$k),'-').': '.$v;$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$send,CURLOPT_POSTFIELDS=>$body,CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_TIMEOUT_MS=>10000]);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);return [$code,$resp===false?'':$resp,$err]; }
function s3_initiate_multipart(string $key,string $mime): string { [$code,$body,$err]=s3_request('POST',$key,'',['uploads'=>'']);if($code<200||$code>=300)throw new RuntimeException('Storage multipart initiation failed.');preg_match('~<UploadId>(.*?)</UploadId>~s',$body,$m);if(empty($m[1]))throw new RuntimeException('Storage did not return an upload ID.');return html_entity_decode($m[1]); }
function s3_complete_multipart(string $key,string $uploadId,array $parts): void { usort($parts,fn($a,$b)=>(int)$a['part']<=>(int)$b['part']);$xml='<CompleteMultipartUpload>';foreach($parts as $p)$xml.='<Part><PartNumber>'.(int)$p['part'].'</PartNumber><ETag>'.htmlspecialchars((string)$p['etag'],ENT_XML1).'</ETag></Part>';$xml.='</CompleteMultipartUpload>';[$code,$body,$err]=s3_request('POST',$key,$xml,['uploadId'=>$uploadId]);if($code<200||$code>=300)throw new RuntimeException('Storage multipart completion failed.'); }
function s3_abort_multipart(string $key,string $uploadId): void { s3_request('DELETE',$key,'',['uploadId'=>$uploadId]); }
function file_detect_mime(string $name): string {
    try {
        if(class_exists('finfo')){ $f=new finfo(FILEINFO_MIME_TYPE); $m=$f->file($name); if($m)return (string)$m; }
    } catch(Throwable $e) {}
    return 'application/octet-stream';
}

function file_relative_path(string $path,string $fallback=''): string {
    $path=str_replace('\\','/',trim($path));$path=preg_replace('#/+#','/',$path)??'';$parts=[];
    foreach(explode('/',$path) as $part){$part=trim($part);if($part===''||$part==='.'||$part==='..')continue;$part=preg_replace('/[^A-Za-z0-9._()\- ]+/u','_',basename($part))?:'_';$parts[]=substr($part,0,180);}
    if(!$parts && $fallback!=='')return file_normalize_name($fallback);return implode('/',$parts);
}
function file_object_key(int $uid,string $token,string $relative,string $name,string $driver): string {
    $safePath=file_relative_path($relative,$name);$dir=trim(str_replace('\\','/',dirname($safePath)),'. /');$base=file_normalize_name(basename($safePath));$leaf=bin2hex(random_bytes(8)).'-'.$base;$prefix=$driver==='s3'?'users/'.$uid.'/'.$token.'/':$uid.'/'.$token.'/';return $prefix.($dir!==''?$dir.'/':'').$leaf;
}
function file_environment_diagnostics(): array {
    $tmp=(string)ini_get('upload_tmp_dir');if($tmp==='')$tmp=sys_get_temp_dir();$root=file_local_root();return ['upload_max_filesize'=>(string)ini_get('upload_max_filesize'),'post_max_size'=>(string)ini_get('post_max_size'),'max_file_uploads'=>(int)ini_get('max_file_uploads'),'max_execution_time'=>(int)ini_get('max_execution_time'),'memory_limit'=>(string)ini_get('memory_limit'),'upload_tmp_writable'=>is_writable($tmp),'storage_root_writable'=>is_writable($root),'local_chunk_bytes'=>file_local_max_bytes()];
}
function file_upload_error_message(int $code): string {
    return match($code){
        UPLOAD_ERR_INI_SIZE=>'The server upload limit was exceeded. Try again with a smaller file or use cloud storage.',
        UPLOAD_ERR_FORM_SIZE=>'The upload form limit was exceeded.',
        UPLOAD_ERR_PARTIAL=>'The upload was interrupted before it finished. Please retry.',
        UPLOAD_ERR_NO_FILE=>'No file data was received by the server.',
        UPLOAD_ERR_NO_TMP_DIR=>'The server temporary upload directory is unavailable.',
        UPLOAD_ERR_CANT_WRITE=>'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION=>'A server extension stopped the upload.',
        default=>'The server could not receive this upload.'
    };
}
function file_upload_info(string $field='file'): array {
    if(!isset($_FILES[$field]) || !is_array($_FILES[$field])) return ['error'=>UPLOAD_ERR_NO_FILE,'tmp'=>'','size'=>0,'name'=>'','mime'=>'application/octet-stream'];
    $f=$_FILES[$field]; $err=(int)($f['error']??UPLOAD_ERR_NO_FILE);
    return ['error'=>$err,'tmp'=>(string)($f['tmp_name']??''),'size'=>(int)($f['size']??0),'name'=>(string)($f['name']??''),'mime'=>(string)($f['type']??'application/octet-stream')];
}
function file_scan_status(string $fileId): string { $s=db()->prepare('SELECT scan_status FROM file_objects WHERE id=?');$s->execute([(int)$fileId]);return (string)($s->fetchColumn()?:'pending'); }
function file_record_version(int $fileId,string $objectKey,int $size,string $etag): void {
    $localPath=file_local_root().'/'.ltrim($objectKey,'/');
    if(is_file($localPath)&&!chmod($localPath,0640))throw new RuntimeException('Could not secure uploaded file permissions.');
    try{$q=db()->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM file_versions WHERE file_id=?');$q->execute([$fileId]);$v=(int)$q->fetchColumn();db()->prepare('INSERT INTO file_versions(file_id,version_no,object_key,size_bytes,etag,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$fileId,$v,$objectKey,$size,$etag]);}catch(Throwable $e){error_log('[PocketCat file version] '.$e->getMessage());}
}
function file_emit(string $event,int $fileId,array $extra=[]): void { try{$q=db()->prepare('SELECT f.public_token,f.user_id FROM file_objects f WHERE f.id=?');$q->execute([$fileId]);$f=$q->fetch();if(!$f)return;$q=db()->prepare('SELECT id,url,secret FROM file_webhooks WHERE user_id=? AND active=1');$q->execute([(int)$f['user_id']]);$payload=['event'=>$event,'file_id'=>$fileId,'token'=>$f['public_token'],'timestamp'=>gmdate('c')]+$extra;$json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);foreach($q->fetchAll() as $h){if(!webhook_target_is_safe((string)$h['url'])){error_log('[PocketCat file webhook] blocked unsafe target for webhook '.(int)$h['id']);continue;}$sig=hash_hmac('sha256',$json,(string)$h['secret']);$ch=curl_init($h['url']);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-PocketCat-Signature: '.$sig],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>500,CURLOPT_TIMEOUT_MS=>2000,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]);curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);db()->prepare('INSERT INTO file_webhook_deliveries(webhook_id,event_name,status_code,created_at) VALUES(?,?,?,NOW())')->execute([(int)$h['id'],$event,$code]);}}catch(Throwable $e){} }
function file_record_event(int $id,string $type,?int $bytes=null,bool $complete=false): void { $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000);[$os,$device,$browser]=detect_device($ua);$ip=client_ip();$country=strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY']??''),0,2));$city=substr((string)($_SERVER['HTTP_CF_CITY']??$_SERVER['HTTP_CF_IPCITY']??''),0,120);$ref=substr((string)($_SERVER['HTTP_REFERER']??''),0,500);$bot=is_bot_ua($ua)?1:0;$vh=visitor_hash($ip,$ua);try{db()->prepare('INSERT INTO file_events(file_id,event_type,visitor_hash,ip_hash,referrer,browser,os,device_category,country_code,city,is_bot,bytes_transferred,completed,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$id,$type,$vh,hash_hmac('sha256',$ip,app_secret()),$ref,$browser,$os,$device,$country,$city,$bot,(int)($bytes??0),$complete?1:0]);if($type==='download'&&!$bot)file_emit('file.downloaded',$id,['bytes'=>$bytes,'completed'=>$complete]);}catch(Throwable $e){} }
function file_public_url(string $token): string { return rtrim((string)app_config()['app_url'],'/').'/file/'.rawurlencode($token); }
function file_share_url(string $token): string { return rtrim((string)app_config()['app_url'],'/').'/file/share/'.rawurlencode($token); }
function file_share_short_url(string $slug): string { return link_public_url($slug); }
function file_share_link_row(int $shareId): ?array { try{$q=db()->prepare('SELECT * FROM short_links WHERE id=? LIMIT 1');$q->execute([$shareId]);$r=$q->fetch();return $r?:null;}catch(Throwable $e){return null;} }
function file_create_tracking_link(int $uid,int $shareId,string $shareToken,string $title,?string $expires,int $maxClicks=0,?string $passwordHash=null,array $advanced=[]): array {
    $slug=link_slug();$destination=rtrim((string)app_config()['app_url'],'/').'/file/share/'.rawurlencode($shareToken);
    $rules=['os'=>[],'country'=>[],'city'=>[]];
    foreach(['iOS','Android','Windows','macOS'] as $os){if(!empty($advanced['os'][$os]))$rules['os'][$os]=normalize_destination((string)$advanced['os'][$os]);}
    if(!empty($advanced['country_code'])&&!empty($advanced['country_url']))$rules['country'][strtoupper((string)$advanced['country_code'])]=normalize_destination((string)$advanced['country_url']);
    $utm=$advanced['utm']??[];$pixels=$advanced['pixels']??[];
    $ogTitle=(string)($advanced['og_title']??$title);$ogDescription=(string)($advanced['og_description']??'Secure file sharing from PocketCat.');$ogImage=(string)($advanced['og_image_url']??'');
    $q=db()->prepare('INSERT INTO short_links(user_id,slug,destination_url,title,password_hash,expires_at,max_clicks,routing_rules,deep_link_url,web_fallback_url,utm_json,tracking_pixels,og_title,og_description,og_image_url,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    $q->execute([$uid,$slug,$destination,$title,$passwordHash,$expires,max(0,$maxClicks),json_encode($rules,JSON_UNESCAPED_SLASHES),trim((string)($advanced['deep_link_url']??'')),trim((string)($advanced['web_fallback_url']??$destination)),json_encode($utm,JSON_UNESCAPED_SLASHES),json_encode($pixels,JSON_UNESCAPED_SLASHES),$ogTitle,$ogDescription,$ogImage]);
    $id=(int)db()->lastInsertId();return ['id'=>$id,'slug'=>$slug,'short_url'=>link_public_url($slug),'destination_url'=>$destination];
}
function file_update_tracking_link(int $linkId,string $title,?string $expires,int $maxClicks=0): void { db()->prepare('UPDATE short_links SET title=?,expires_at=?,max_clicks=?,updated_at=NOW() WHERE id=?')->execute([$title,$expires,max(0,$maxClicks),$linkId]); }
function file_catalog_sync(array $f): void {
    if(function_exists('asset_catalog_register_file')){
        $sourceType=(string)($f['source_type']??'storage');
        $category=(string)($f['source_category']??asset_category($sourceType));
        asset_catalog_register_file((int)$f['user_id'],(int)$f['id'],(string)$f['original_name'],file_public_url((string)$f['public_token']), (string)$f['mime_type'],(int)$f['size_bytes'],(string)$f['kind'],$sourceType,$category);
    }
}
function file_unique_media_name(int $uid,string $folder,string $name): string {
    $name=file_normalize_name($name);
    if($name==='') return 'file';
    $stem=pathinfo($name,PATHINFO_FILENAME);
    $ext=pathinfo($name,PATHINFO_EXTENSION);
    $suffix=$ext!==''?'.'.$ext:'';
    $candidate=$name;$n=0;
    $q=db()->prepare("SELECT 1 FROM media_assets WHERE user_id=? AND folder_name=? AND name=? AND name<>'.folder-marker' LIMIT 1");
    while(true){
        $q->execute([$uid,$folder,$candidate]);
        if(!$q->fetchColumn()) return $candidate;
        $n++;
        $candidate=substr($stem,0,max(1,220-strlen($suffix)-strlen((string)$n)-3)).' ('.$n.')'.$suffix;
    }
}
function file_create_from_media_upload(array $r,int $uid,string $folder): array {
    $path=(string)($r['path']??'');
    $url=(string)($r['url']??'');
    if($path==='' || $url==='' || !is_file($path)) throw new RuntimeException('Media upload final file is unavailable.');
    $prefix=__DIR__.'/../uploads/media/'.$uid.'/';
    if(!str_starts_with($path,$prefix)) throw new RuntimeException('Invalid media storage path.');
    $objectKey='media/'.$uid.'/'.ltrim(substr($path,strlen($prefix)),'/');
    $db=db();$lock=file_unique_upload_lock($uid);
    try {
        $name=file_unique_media_name($uid,$folder,(string)$r['name']);
        $relative=(string)($r['relative_path']??$name);
        $relative=trim(str_replace('\\','/',$relative),'/');
        if($relative!==''){$parts=array_values(array_filter(explode('/',$relative),fn($v)=>$v!==''&&$v!=='.'&&$v!=='..'));if($parts){$parts[count($parts)-1]=$name;$relative=implode('/',$parts);}else{$relative=$name;}}else{$relative=$name;}
        $mime=(string)$r['mime'];$size=(int)$r['size'];$kind=file_kind($mime);$token=file_slug();$etag=hash_file('sha256',$path);
        $db->beginTransaction();
        $db->prepare("INSERT INTO file_objects(user_id,public_token,object_key,original_name,relative_path,mime_type,size_bytes,kind,source_type,source_category,storage_driver,status,scan_status,etag,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
           ->execute([$uid,$token,$objectKey,(string)$name,$relative,$mime,$size,$kind,'media','Media Assets','local','clean','clean',$etag]);
        $fileId=(int)$db->lastInsertId();
        $width=$height=null;if($kind==='image'){ $i=@getimagesize($path);$width=$i[0]??null;$height=$i[1]??null; }
        $sourceUrl=file_asset_url(['id'=>$fileId,'public_token'=>$token,'object_key'=>$objectKey,'storage_driver'=>'local','mime_type'=>$mime]);
        $db->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_category,source_ref_id,canonical_file_id,source_url,storage_path,mime_type,size_bytes,kind,width,height,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())')
           ->execute([$uid,$name,$folder,'upload','Media Assets',0,$fileId,$sourceUrl,$objectKey,$mime,$size,$kind,$width,$height]);
        $mediaId=(int)$db->lastInsertId();
        $db->prepare('UPDATE media_assets SET source_ref_id=? WHERE id=? AND user_id=?')->execute([$mediaId,$mediaId,$uid]);
        file_record_version($fileId,$objectKey,$size,$etag);
        $db->commit();
        return ['id'=>$mediaId,'file_id'=>$fileId,'url'=>$sourceUrl,'name'=>$name,'folder'=>$folder,'kind'=>$kind,'category'=>'Media Assets'];
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
    finally { file_unique_upload_unlock($lock); }
}

function file_record_share_event(int $shareId,string $type,?int $fileId=null): void { try{db()->prepare('INSERT INTO file_share_events(share_id,file_id,event_type,visitor_hash,ip_hash,referrer,user_agent,occurred_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$shareId,$fileId,substr($type,0,40),visitor_hash(client_ip(),(string)($_SERVER['HTTP_USER_AGENT']??'')),hash_hmac('sha256',client_ip(),app_secret()),substr((string)($_SERVER['HTTP_REFERER']??''),0,500),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000)]);}catch(Throwable $e){} }

function file_share_token(): string { do{$t='fs_'.bin2hex(random_bytes(22));$q=db()->prepare('SELECT 1 FROM file_share_links WHERE token=?');$q->execute([$t]);}while($q->fetchColumn());return $t; }
function file_preview_allowed(string $mime): bool { return str_starts_with($mime,'image/')||str_starts_with($mime,'audio/')||str_starts_with($mime,'video/')||$mime==='application/pdf'||str_starts_with($mime,'text/'); }
function file_download_url(array $f,int $ttl=300): string { if(!empty($f['provider_url']) && in_array((string)($f['storage_driver']??''),['google_drive','onedrive','dropbox'],true))return (string)$f['provider_url']; if(($f['storage_driver']??'s3')==='local'||!s3_cfg_ok())return file_local_url($f,true); $name=basename(str_replace(["\r","\n"],'',(string)($f['original_name']??'download'))); if($name==='')$name='download'; return s3_presigned_url('GET',(string)$f['object_key'],$ttl,(string)$f['mime_type'],['response-content-disposition'=>'attachment; filename="'.str_replace(['\\','"'],'_', $name).'"']); }
function file_asset_url(array $f): string { if(!empty($f['provider_url']) && in_array((string)($f['storage_driver']??''),['google_drive','onedrive','dropbox'],true))return (string)$f['provider_url']; return (($f['storage_driver']??'s3')==='local'||!s3_cfg_ok()) ? file_local_url($f,false) : s3_presigned_url('GET',(string)$f['object_key'],300,(string)$f['mime_type']); }
