<?php
declare(strict_types=1);

function cloud_storage_providers(): array {
    return [
        's3'=>['label'=>'S3-compatible','kind'=>'s3'],
        'google_drive'=>['label'=>'Google Drive','kind'=>'oauth'],
        'onedrive'=>['label'=>'Microsoft OneDrive','kind'=>'oauth'],
        'dropbox'=>['label'=>'Dropbox','kind'=>'oauth'],
        'local'=>['label'=>'InfinityFree Local','kind'=>'local'],
    ];
}
function cloud_provider_label(string $p): string { return cloud_storage_providers()[$p]['label'] ?? ucfirst(str_replace('_',' ',$p)); }
function cloud_storage_folder(): string { $v=trim((string)file_setting('storage_folder','PocketCat')); $v=preg_replace('/[^A-Za-z0-9._ -]+/','-', $v) ?: 'PocketCat'; return trim(substr($v,0,120)) ?: 'PocketCat'; }
function cloud_secret_key(string $provider,string $name): string { return 'file_'.$provider.'_'.$name; }
function cloud_client_id(string $provider): string { return (string)setting('file_'.$provider.'_client_id',''); }
function cloud_client_secret(string $provider): string { return (string)secret_setting('file_'.$provider.'_client_secret',''); }
function cloud_connection(string $provider): ?array {
    try { $s=db()->prepare('SELECT * FROM storage_connections WHERE provider=? LIMIT 1'); $s->execute([$provider]); $r=$s->fetch(); if(!$r)return null; $r['access_token']=decrypt_secret((string)($r['access_token_encrypted']??'')); $r['refresh_token']=decrypt_secret((string)($r['refresh_token_encrypted']??'')); return $r; } catch(Throwable $e){ return null; }
}
function cloud_connection_status(string $provider): array { $r=cloud_connection($provider); return ['connected'=>!!($r&&($r['refresh_token']!==''||$r['access_token']!=='')),'email'=>(string)($r['account_email']??''),'expires_at'=>(string)($r['expires_at']??'')]; }
function cloud_oauth_redirect_uri(string $provider): string { return rtrim((string)app_config()['app_url'],'/').'/admin/storage/oauth/?provider='.rawurlencode($provider); }
function cloud_with_mock_transport(?callable $transport,callable $operation): mixed {
    if($transport===null)return $operation();
    if(PHP_SAPI!=='cli')throw new RuntimeException('Mock cloud transport is available only to CLI tests.');
    $hadPrevious=array_key_exists('pocketcat_cloud_mock_transport',$GLOBALS);$previous=$GLOBALS['pocketcat_cloud_mock_transport']??null;
    $GLOBALS['pocketcat_cloud_mock_transport']=$transport;
    try{return $operation();}
    finally{if($hadPrevious)$GLOBALS['pocketcat_cloud_mock_transport']=$previous;else unset($GLOBALS['pocketcat_cloud_mock_transport']);}
}
function cloud_http(string $method,string $url,array $headers=[],?string $body=null): array {
    $transport=$GLOBALS['pocketcat_cloud_mock_transport']??null;
    if(PHP_SAPI==='cli'&&is_callable($transport)){
        $response=$transport($method,$url,$headers,$body);
        if(!is_array($response)||!isset($response[0],$response[1])||!is_numeric($response[0])||!is_string($response[1]))throw new RuntimeException('Cloud mock transport returned an invalid response.');
        return [(int)$response[0],$response[1]];
    }
    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for cloud storage.');
    $ch=curl_init($url);$opts=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];if($body!==null)$opts[CURLOPT_POSTFIELDS]=$body;curl_setopt_array($ch,$opts);$out=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($out===false)throw new RuntimeException('Cloud request failed: '.$err);return [$code,(string)$out];
}
function cloud_refresh_token(string $provider,array $conn): string {
    $refresh=(string)($conn['refresh_token']??''); if($refresh==='')return (string)($conn['access_token']??'');
    if(!empty($conn['expires_at']) && strtotime((string)$conn['expires_at'])>time()+120)return (string)$conn['access_token'];
    $client=cloud_client_id($provider);$secret=cloud_client_secret($provider);if($client===''||$secret==='')throw new RuntimeException('OAuth client credentials are not configured for '.cloud_provider_label($provider).'.');
    if($provider==='google_drive'){
        [$code,$raw]=cloud_http('POST','https://oauth2.googleapis.com/token',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['client_id'=>$client,'client_secret'=>$secret,'refresh_token'=>$refresh,'grant_type'=>'refresh_token']));
    }elseif($provider==='onedrive'){
        [$code,$raw]=cloud_http('POST','https://login.microsoftonline.com/common/oauth2/v2.0/token',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['client_id'=>$client,'client_secret'=>$secret,'refresh_token'=>$refresh,'grant_type'=>'refresh_token','scope'=>'offline_access Files.ReadWrite.All User.Read']));
    }else{return (string)$conn['access_token'];}
    $d=json_decode($raw,true);if($code<200||$code>=300||empty($d['access_token']))throw new RuntimeException('Cloud token refresh failed.');$access=(string)$d['access_token'];$expires=(int)($d['expires_in']??3600);$newRefresh=(string)($d['refresh_token']??$refresh);db()->prepare('UPDATE storage_connections SET access_token_encrypted=?,refresh_token_encrypted=?,expires_at=?,updated_at=NOW() WHERE provider=?')->execute([encrypt_secret($access),encrypt_secret($newRefresh),date('Y-m-d H:i:s',time()+$expires),$provider]);return $access;
}
function cloud_oauth_authorize_url(string $provider): string {
    $client=cloud_client_id($provider);if($client==='')throw new RuntimeException('Enter the '.$provider.' OAuth Client ID first.');$redirect=cloud_oauth_redirect_uri($provider);$state=bin2hex(random_bytes(24));$_SESSION['cloud_oauth_state']=$state;$_SESSION['cloud_oauth_provider']=$provider;
    if($provider==='google_drive')return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$client,'redirect_uri'=>$redirect,'response_type'=>'code','access_type'=>'offline','prompt'=>'consent','scope'=>'https://www.googleapis.com/auth/drive.file','state'=>$state]);
    if($provider==='onedrive')return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'.http_build_query(['client_id'=>$client,'redirect_uri'=>$redirect,'response_type'=>'code','response_mode'=>'query','scope'=>'offline_access Files.ReadWrite.All User.Read','state'=>$state]);
    if($provider==='dropbox')return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query(['client_id'=>$client,'redirect_uri'=>$redirect,'response_type'=>'code','token_access_type'=>'offline','state'=>$state]);
    throw new RuntimeException('OAuth is not supported for this driver.');
}
function cloud_oauth_callback(string $provider,string $code,?callable $transport=null): void {
    cloud_with_mock_transport($transport,static function() use($provider,$code): void {
    $client=cloud_client_id($provider);$secret=cloud_client_secret($provider);$redirect=cloud_oauth_redirect_uri($provider);if($client===''||$secret==='')throw new RuntimeException('OAuth client ID/secret is missing.');
    if($provider==='google_drive'){$url='https://oauth2.googleapis.com/token';$body=['client_id'=>$client,'client_secret'=>$secret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redirect];}
    elseif($provider==='onedrive'){$url='https://login.microsoftonline.com/common/oauth2/v2.0/token';$body=['client_id'=>$client,'client_secret'=>$secret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redirect,'scope'=>'offline_access Files.ReadWrite.All User.Read'];}
    elseif($provider==='dropbox'){$url='https://api.dropboxapi.com/oauth2/token';$body=['client_id'=>$client,'client_secret'=>$secret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redirect];}
    else throw new RuntimeException('Unsupported OAuth provider.');
    [$status,$raw]=cloud_http('POST',$url,['Content-Type: application/x-www-form-urlencoded'],http_build_query($body));$d=json_decode($raw,true);if($status<200||$status>=300||empty($d['access_token']))throw new RuntimeException('OAuth token exchange failed.');$access=(string)$d['access_token'];$refresh=(string)($d['refresh_token']??'');$email='';
    try{if($provider==='google_drive'){[$c,$r]=cloud_http('GET','https://www.googleapis.com/drive/v3/about?fields=user(emailAddress)', ['Authorization: Bearer '.$access]);$email=(string)(json_decode($r,true)['user']['emailAddress']??'');}elseif($provider==='onedrive'){[$c,$r]=cloud_http('GET','https://graph.microsoft.com/v1.0/me?$select=userPrincipalName,mail,displayName',['Authorization: Bearer '.$access]);$x=json_decode($r,true);$email=(string)($x['mail']??$x['userPrincipalName']??'');}elseif($provider==='dropbox'){[$c,$r]=cloud_http('POST','https://api.dropboxapi.com/2/users/get_current_account',['Authorization: Bearer '.$access,'Content-Type: application/json'],'{}');$email=(string)(json_decode($r,true)['email']??'');}}catch(Throwable $e){}
    db()->prepare('INSERT INTO storage_connections(provider,account_email,access_token_encrypted,refresh_token_encrypted,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE account_email=VALUES(account_email),access_token_encrypted=VALUES(access_token_encrypted),refresh_token_encrypted=VALUES(refresh_token_encrypted),expires_at=VALUES(expires_at),updated_at=NOW()')->execute([$provider,$email,encrypt_secret($access),encrypt_secret($refresh),date('Y-m-d H:i:s',time()+(int)($d['expires_in']??3600))]);
    });
}
function cloud_upload_bytes(string $provider,string $folder,string $name,string $mime,string $bytes,?callable $transport=null): array {
    return cloud_with_mock_transport($transport,static function() use($provider,$folder,$name,$mime,$bytes): array {
    $conn=cloud_connection($provider);if(!$conn||(!$conn['access_token']&&!$conn['refresh_token']))throw new RuntimeException('Connect '.cloud_provider_label($provider).' before uploading.');$token=cloud_refresh_token($provider,$conn);
    if($provider==='google_drive'){
        $folderId='';[$fc,$fr]=cloud_http('GET','https://www.googleapis.com/drive/v3/files?spaces=drive&fields=files(id,name)&q='.rawurlencode("name='".str_replace("'","\'",$folder)."' and mimeType='application/vnd.google-apps.folder' and trashed=false"),['Authorization: Bearer '.$token]);$fd=json_decode($fr,true);$folderId=(string)($fd['files'][0]['id']??'');if($folderId===''){[$fc,$fr]=cloud_http('POST','https://www.googleapis.com/drive/v3/files',['Authorization: Bearer '.$token,'Content-Type: application/json'],json_encode(['name'=>$folder,'mimeType'=>'application/vnd.google-apps.folder']));$folderId=(string)(json_decode($fr,true)['id']??'');}
        $meta=json_encode(['name'=>$name,'mimeType'=>$mime,'parents'=>$folderId?[$folderId]:[]],JSON_UNESCAPED_SLASHES);[$code,$raw]=cloud_http('POST','https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink,name', ['Authorization: Bearer '.$token,'Content-Type: multipart/related; boundary=pocketcat'], "--pocketcat\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n--pocketcat\r\nContent-Type: {$mime}\r\n\r\n{$bytes}\r\n--pocketcat--\r\n");$d=json_decode($raw,true);if($code<200||$code>=300||empty($d['id']))throw new RuntimeException('Google Drive upload failed.');$public='';try{cloud_http('POST','https://www.googleapis.com/drive/v3/files/'.rawurlencode($d['id']).'/permissions',['Authorization: Bearer '.$token,'Content-Type: application/json'],json_encode(['role'=>'reader','type'=>'anyone']));}catch(Throwable $e){}[$lc,$lr]=cloud_http('GET','https://www.googleapis.com/drive/v3/files/'.rawurlencode($d['id']).'?fields=webViewLink,webContentLink',['Authorization: Bearer '.$token]);$ld=json_decode($lr,true);$public=(string)($ld['webContentLink']??$ld['webViewLink']??('https://drive.google.com/open?id='.$d['id']));return ['id'=>$d['id'],'url'=>$public];
    }
    if($provider==='onedrive'){$path=rawurlencode($folder).'/'.rawurlencode($name);$path=str_replace('%2F','/',$path);[$code,$raw]=cloud_http('PUT','https://graph.microsoft.com/v1.0/me/drive/root:/'.$path.':/content',['Authorization: Bearer '.$token,'Content-Type'=>$mime],$bytes);$d=json_decode($raw,true);if($code<200||$code>=300||empty($d['id']))throw new RuntimeException('OneDrive upload failed.');$url=(string)($d['webUrl']??'');try{[$lc,$lr]=cloud_http('POST','https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($d['id']).'/createLink',['Authorization: Bearer '.$token,'Content-Type: application/json'],json_encode(['type'=>'view','scope'=>'anonymous']));$ld=json_decode($lr,true);$url=(string)($ld['link']['webUrl']??$url);}catch(Throwable $e){}return ['id'=>$d['id'],'url'=>$url];}
    if($provider==='dropbox'){$path='/'.trim($folder,'/').'/'.$name;[$code,$raw]=cloud_http('POST','https://content.dropboxapi.com/2/files/upload',['Authorization: Bearer '.$token,'Content-Type: application/octet-stream','Dropbox-API-Arg'=>json_encode(['path'=>$path,'mode'=>'add','autorename'=>true,'mute'=>false])],$bytes);$d=json_decode($raw,true);if($code<200||$code>=300||empty($d['id']))throw new RuntimeException('Dropbox upload failed.');[$sc,$sr]=cloud_http('POST','https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings',['Authorization: Bearer '.$token,'Content-Type: application/json'],json_encode(['path'=>$d['path_display']]));$sd=json_decode($sr,true);$url=(string)($sd['url']??'');return ['id'=>$d['id'],'url'=>$url];}
    throw new RuntimeException('Unsupported cloud storage driver.');
    });
}
