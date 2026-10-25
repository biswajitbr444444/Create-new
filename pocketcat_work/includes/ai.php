<?php
function ai_estimate_units(string $text):int{
    $text=trim($text); if($text==='')return 1;
    preg_match_all('/\S+/u',$text,$m); return max(1,count($m[0]));
}
function ai_usage_limits(?array $u=null):array{
    $u=$u?:current_user();$p=plan_limits((string)($u['plan']??'free'));
    return ['seconds'=>(int)$p['ai_seconds']];
}
function ai_usage_limit():int{return ai_usage_limits()['seconds'];}
function ai_usage(int $uid):array{
    try{
        $s=db()->prepare('SELECT active_seconds FROM ai_usage_daily WHERE user_id=? AND usage_date=CURDATE()');$s->execute([$uid]);$r=$s->fetch();
        return $r?:['active_seconds'=>0];
    }catch(Throwable $e){return ['active_seconds'=>0];}
}
function ai_is_premium(?array $u=null):bool{$u=$u?:current_user();return function_exists('policy_testing_mode')&&policy_testing_mode() || ($u&&((($u['role']??'')==='admin')||(($u['plan']??'free')==='premium')));}
function ai_seconds_remaining(int $uid,?array $u=null):float{
    $u=$u?:current_user();if(ai_is_premium($u))return INF;
    $lim=ai_usage_limits($u)['seconds'];$x=ai_usage($uid);return max(0.0,$lim-(float)($x['active_seconds']??0));
}
function ai_input_remaining(int $uid,?array $u=null):int{ $x=ai_seconds_remaining($uid,$u); return is_infinite($x)?PHP_INT_MAX:(int)floor($x); }
function ai_output_remaining(int $uid,?array $u=null):int{ return ai_input_remaining($uid,$u); }
function ai_remaining(int $uid,?array $u=null):int{return ai_input_remaining($uid,$u);}
function ai_remaining_label(int $uid,?array $u=null):string{
    $u=$u?:current_user();if(ai_is_premium($u))return 'Unlimited AI time';
    $sec=ai_seconds_remaining($uid,$u);$h=floor($sec/3600);$m=floor(($sec%3600)/60);$s=floor($sec%60);
    if($h>0)return $h.'h '.str_pad((string)$m,2,'0',STR_PAD_LEFT).'m remaining today';
    return $m.'m '.str_pad((string)$s,2,'0',STR_PAD_LEFT).'s remaining today';
}
function ai_reserve_input(int $uid,int $units):bool{
    // Backward-compatible gate for older callers. No tokens are consumed anymore.
    return ai_is_premium() || ai_seconds_remaining($uid)>=0.001;
}
function ai_release_input(int $uid,int $units):void{ /* Token quota removed; nothing to release. */ }
function ai_add_output(int $uid,int $units):void{ /* Token quota removed; active time is charged by ai_add_active_time(). */ }
function ai_add_active_time(int $uid,float $seconds,?array $u=null):void{
    $u=$u?:current_user();if(ai_is_premium($u)||$seconds<=0)return;
    $remaining=ai_seconds_remaining($uid,$u);$charge=min($seconds,$remaining);if($charge<=0)return;
    try{
        $s=db()->prepare('INSERT INTO ai_usage_daily(user_id,usage_date,input_units,output_units,active_seconds,updated_at) VALUES(?,CURDATE(),0,0,?,NOW()) ON DUPLICATE KEY UPDATE active_seconds=active_seconds+VALUES(active_seconds),updated_at=NOW()');
        $s->execute([$uid,round($charge,3)]);
    }catch(Throwable $e){}
}
function ai_global_credentials():array{
    return [
        'api_key'=>secret_setting('bedrock_api_key',''),
        'access_key'=>secret_setting('aws_access_key_id',''),
        'secret_key'=>secret_setting('aws_secret_access_key',''),
        'session_token'=>secret_setting('aws_session_token',''),
        'region'=>setting('aws_region','us-east-1'),
        'model'=>setting('bedrock_model_id','openai.gpt-oss-120b'),
        'endpoint'=>rtrim((string)setting('bedrock_endpoint_url',''),'/'),
        'api_mode'=>setting('bedrock_api_mode','openai'),
        'auth_type'=>setting('bedrock_auth_type','api_key'),
        'transport'=>setting('bedrock_transport','relay'),
        'relay_url'=>rtrim((string)setting('bedrock_relay_url',''),'/'),
        'relay_token'=>secret_setting('bedrock_relay_token',''),
        'source'=>'admin',
    ];
}
function ai_user_credentials(int $uid):?array{
    if($uid<=0)return null;
    try{$s=db()->prepare('SELECT * FROM user_ai_settings WHERE user_id=?');$s->execute([$uid]);$r=$s->fetch();if(!$r)return null;
        return [
            'api_key'=>decrypt_secret((string)($r['bedrock_api_key_encrypted']??'')),
            'access_key'=>decrypt_secret((string)($r['aws_access_key_encrypted']??'')),
            'secret_key'=>decrypt_secret((string)($r['aws_secret_key_encrypted']??'')),
            'session_token'=>decrypt_secret((string)($r['aws_session_token_encrypted']??'')),
            'region'=>(string)($r['region']??''),'model'=>(string)($r['model_id']??''),'endpoint'=>rtrim((string)($r['endpoint_url']??''),'/'),
            'api_mode'=>(string)($r['api_mode']??''),'transport'=>(string)($r['transport']??''),'relay_url'=>rtrim((string)($r['relay_url']??''),'/'),
            'relay_token'=>decrypt_secret((string)($r['relay_token_encrypted']??'')),'source'=>'user'
        ];
    }catch(Throwable $e){return null;}
}
function ai_credentials(?int $uid=null):array{
    $global=ai_global_credentials(); if(!$uid)return $global;
    $user=ai_user_credentials($uid); if(!$user)return $global;
    foreach(['api_key','access_key','secret_key','session_token','region','model','endpoint','api_mode','transport','relay_url','relay_token'] as $k){
        if(isset($user[$k]) && (string)$user[$k]!=='')$global[$k]=$user[$k];
    }
    $global['source']='user'; return $global;
}
function ai_user_settings_status(int $uid):array{
    $r=ai_user_credentials($uid);$g=ai_global_credentials();$effective=$uid>0?ai_credentials($uid):$g;
    $configured=ai_uses_relay($effective)
        ?(($effective['relay_url']??'')!==''&&($effective['relay_token']??'')!=='')
        :(($effective['api_key']??'')!==''||(($effective['access_key']??'')!==''&&($effective['secret_key']??'')!==''));
    return [
        'configured'=>$configured,
        'api_key'=>(bool)($r['api_key']??''),'access_key'=>(bool)($r['access_key']??''),'secret_key'=>(bool)($r['secret_key']??''),'session_token'=>(bool)($r['session_token']??''),'relay_token'=>(bool)($r['relay_token']??''),
        'region'=>(string)($r['region']??$g['region']),'model'=>(string)($r['model']??$g['model']),'endpoint'=>(string)($r['endpoint']??$g['endpoint']),
        'api_mode'=>(string)($r['api_mode']??$g['api_mode']),'transport'=>(string)($r['transport']??$g['transport']),'relay_url'=>(string)($r['relay_url']??$g['relay_url']),
        'source'=>(string)($r['source']??'admin')
    ];
}
function ai_mask_secret(string $v):string{ if($v==='')return 'Not configured'; $n=strlen($v); return $n<=8?str_repeat('•',max(0,$n-4)).substr($v,-4):str_repeat('•',8).substr($v,-4); }
function ai_save_user_settings(int $uid,array $data):void{
    $allowed=['region','model_id','endpoint_url','api_mode','transport','relay_url'];$vals=[];foreach($allowed as $k)$vals[$k]=trim((string)($data[$k]??''));
    $secretMap=['bedrock_api_key'=>'bedrock_api_key_encrypted','aws_access_key_id'=>'aws_access_key_encrypted','aws_secret_access_key'=>'aws_secret_key_encrypted','aws_session_token'=>'aws_session_token_encrypted','bedrock_relay_token'=>'relay_token_encrypted'];
    $transport=(string)$vals['transport'];
    $activeSecrets=$transport==='relay'?['bedrock_relay_token']:(($transport==='direct'&&$vals['api_mode']==='converse')?['aws_access_key_id','aws_secret_access_key','aws_session_token']:['bedrock_api_key']);
    foreach($activeSecrets as $in){$col=$secretMap[$in];$v=trim((string)($data[$in]??''));if($v!==''&&!secret_is_masked_input($v))$vals[$col]=encrypt_secret($v);}
    $existing=false;try{$q=db()->prepare('SELECT user_id FROM user_ai_settings WHERE user_id=?');$q->execute([$uid]);$existing=(bool)$q->fetchColumn();}catch(Throwable $e){}
    if(!$existing){
        $cols=['user_id'];$place=['?'];$params=[$uid];foreach($allowed as $k){$cols[]=$k;$place[]='?';$params[]=$vals[$k];}foreach($secretMap as $in=>$col){$cols[]=$col;$place[]='?';$params[]=$vals[$col]??null;}db()->prepare('INSERT INTO user_ai_settings('.implode(',',$cols).',updated_at) VALUES('.implode(',',$place).',NOW())')->execute($params);
    }else{
        $sets=[];$params=[];foreach($allowed as $k){$sets[]="$k=?";$params[]=$vals[$k];}foreach($secretMap as $in=>$col){if(array_key_exists($col,$vals)){$sets[]="$col=?";$params[]=$vals[$col];}}$params[]=$uid;db()->prepare('UPDATE user_ai_settings SET '.implode(',',$sets).',updated_at=NOW() WHERE user_id=?')->execute($params);
    }
    foreach($secretMap as $in=>$col){if(($data['clear_'.$in]??'')==='1')db()->prepare("UPDATE user_ai_settings SET $col=NULL,updated_at=NOW() WHERE user_id=?")->execute([$uid]);}
}
function ai_valid_model_id(string $model):bool{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,189}$/D',trim($model))===1;
}
function ai_user_model_preference(int $uid):string{
    if($uid<=0)return '';
    try{$s=db()->prepare('SELECT model_id FROM ai_user_preferences WHERE user_id=?');$s->execute([$uid]);return trim((string)$s->fetchColumn());}catch(Throwable $e){return '';}
}
function ai_save_user_model_preference(int $uid,string $model):void{
    $model=trim($model);
    if($uid<=0||!ai_valid_model_id($model))throw new InvalidArgumentException('Enter a valid model ID.');
    db()->prepare('INSERT INTO ai_user_preferences(user_id,model_id,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE model_id=VALUES(model_id),updated_at=NOW()')->execute([$uid,$model]);
}
function ai_is_legacy_error_html(string $text):bool{
    return preg_match('/<!doctype\s+html|<\s*(?:html|style|script)\b/i',$text)===1
        || (stripos($text,'403 forbidden')!==false && preg_match('/(?:directory listing|infinityfree|apache|nginx)/i',$text)===1);
}
function ai_safe_provider_error(string $message):string{
    $message=trim($message);
    if($message===''||ai_is_legacy_error_html($message))return 'The AI provider returned an error. Check the connection settings and server logs.';
    return mb_substr(strip_tags($message),0,600);
}
function ai_openai_compatible(array $c):bool{
    $mode=(string)($c['api_mode']??'auto');
    if($mode==='responses'||$mode==='openai')return true;
    if($mode==='converse')return false;
    $endpoint=(string)($c['endpoint']??'');
    $model=(string)($c['model']??'');
    return $c['api_key']!=='' && (stripos($endpoint,'/openai/v1')!==false || stripos($endpoint,'/responses')!==false || str_starts_with($model,'openai.'));
}
function ai_openai_base_url(array $c):string{
    $endpoint=rtrim((string)($c['endpoint']??''),'/');
    $model=(string)($c['model']??'');
    // Keep an explicitly configured Bedrock endpoint intact. Current AWS
    // OpenAI-compatible APIs support gpt-oss-120b on both bedrock-runtime
    // (/openai/v1) and bedrock-mantle (/v1). Do not silently rewrite one to
    // the other because that can make a valid customer configuration fail.
    $mantle='https://bedrock-mantle.'.rawurlencode((string)$c['region']).'.api.aws/v1';
    if($endpoint!==''){
        $endpoint=preg_replace('~/(?:responses|chat/completions)$~','',$endpoint);
        if(preg_match('~/openai/v1$~',$endpoint))return $endpoint;
        if(preg_match('~/v1$~',$endpoint))return $endpoint;
        return $endpoint.'/openai/v1';
    }
    return $mantle;
}

function ai_url(string $region,string $model,string $action,string $endpoint=''):string{
    $model=trim($model);
    if($endpoint!==''){
        if(strpos($endpoint,'{model}')!==false||strpos($endpoint,'{action}')!==false)return str_replace(['{model}','{action}'],[rawurlencode($model),$action],$endpoint);
        if(preg_match('~/model/[^/]+/(?:converse|converse-stream)$~',$endpoint))return preg_replace('~/(?:converse|converse-stream)$~','/'.$action,$endpoint);
        if(preg_match('~/converse(?:-stream)?$~',$endpoint))return preg_replace('~/converse(?:-stream)?$~','/'.$action,$endpoint);
        return rtrim($endpoint,'/').'/model/'.rawurlencode($model).'/'.$action;
    }
    return 'https://bedrock-runtime.'.rawurlencode($region).'.amazonaws.com/model/'.rawurlencode($model).'/'.$action;
}

function ai_network_test(?int $uid=null):array{
    $c=ai_credentials($uid);
    if(($c['transport']??'auto')==='relay' || $c['relay_url']!==''){
        if($c['relay_url']==='')return [false,'Relay mode is enabled but the Relay URL is empty.'];
        $url=rtrim($c['relay_url'],'/').'/health';
        $parts=parse_url($url);$host=$parts['host']??'';
        if($host==='')return [false,'Invalid Bedrock relay URL.'];
        $resolved=gethostbyname($host);
        if($resolved===$host)return [false,"DNS lookup failed for relay host $host."];
        if(!function_exists('curl_init'))return [false,'PHP cURL is not enabled on this server.'];
        $headers=['accept'=>'application/json'];
        if($c['relay_token']!=='')$headers['x-pocketcat-relay-token']=$c['relay_token'];
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>false,CURLOPT_NOBODY=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>array_map(fn($k)=>$k.': '.$headers[$k],array_keys($headers)),CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $out=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ip=(string)curl_getinfo($ch,CURLINFO_PRIMARY_IP);curl_close($ch);
        if($out===false)return [false,"Relay network error (cURL #$errno): $err. InfinityFree must allow outbound HTTPS to the relay host."];
        if($http<200||$http>=300)return [false,"Relay returned HTTP $http. Check the Relay URL and shared token."];
        return [true,"HTTPS reached relay $host (IP $ip) with HTTP $http."];
    }
    $region=trim((string)$c['region']);
    if($region==='')return [false,'AWS Region is empty.'];
    $url=rtrim($c['endpoint']!==''?$c['endpoint']:'https://bedrock-runtime.'.rawurlencode($region).'.amazonaws.com','/').'/';
    $parts=parse_url($url);$host=$parts['host']??'';
    if($host==='')return [false,'Invalid Bedrock endpoint.'];
    $resolved=gethostbyname($host);
    if($resolved===$host)return [false,"DNS lookup failed for $host. The hosting server cannot resolve the AWS hostname."];
    if(!function_exists('curl_init'))return [false,'PHP cURL is not enabled on this server.'];
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>false,CURLOPT_NOBODY=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ip=(string)curl_getinfo($ch,CURLINFO_PRIMARY_IP);$connect=(float)curl_getinfo($ch,CURLINFO_CONNECT_TIME);curl_close($ch);
    if($errno!==0){
        if($errno===7)return [false,"DNS resolved $host to $resolved, but the server could not open TCP/HTTPS connection to port 443 (cURL #7). This is a hosting outbound-network restriction, not an AWS API-key or model error."];
        return [false,"Network request failed (cURL #$errno): $err. Resolved IP: $resolved."];
    }
    return [true,"HTTPS reached $host (IP $ip) with HTTP $http in ".number_format($connect,3).'s. A 4xx/404 here can be normal because this was only a connectivity check.'];
}

function ai_test_connection(?int $uid=null):array{
    $c=ai_credentials($uid);
    // Relay mode intentionally does not require Bedrock credentials on the
    // PHP host. The Worker owns the Bedrock API key and forwards the request.
    // This mirrors Cline's separation between provider configuration and the
    // actual transport credentials, and avoids a false failure on shared
    // hosting when only the relay token is configured here.
    $usingRelay=ai_uses_relay($c);
    if(!$usingRelay && !$c['api_key']&&(!$c['access_key']||!$c['secret_key']))return [false,'No Bedrock credentials are configured. Save an API key or AWS Access Key / Secret Key first.'];
    if($c['region']===''||$c['model']==='')return [false,'AWS Region and model / inference profile ID are required.'];
    if($usingRelay && $c['relay_token']==='')return [false,'Relay is enabled but the shared relay token is missing. Save the same RELAY_TOKEN used by the Cloudflare Worker.'];
    if(ai_openai_compatible($c)){
        // Use Chat Completions for the health check: it is the same simple
        // OpenAI-compatible path used by normal PocketCat chat and avoids
        // false 'HTTP 200 but no text' results from reasoning-only responses.
        [$ok,$text]=ai_chat_completions_request([['role'=>'user','content'=>'Reply with exactly: PocketCat Bedrock connection OK']],$c,4096);
        return $ok?[true,'Bedrock OpenAI-compatible connection OK: '.$text]:[false,$text];
    }
    $payload=json_encode(['messages'=>[['role'=>'user','content'=>[['text'=>'Reply with exactly: PocketCat Bedrock connection OK']]]],'inferenceConfig'=>['maxTokens'=>16,'temperature'=>0]],JSON_UNESCAPED_SLASHES);
    $url=ai_url($c['region'],$c['model'],'converse',$c['endpoint']);
    [$ok,$raw]=ai_http($url,$payload,$c,false);
    if(!$ok)return [false,$raw];
    $data=json_decode($raw,true);$text=ai_extract_text($data);
    return [true,$text!==''?'Bedrock connection OK: '.$text:'Bedrock returned HTTP 200, but no text content was present.'];
}
function ai_openai_extract_text(array $data):string{
    if(isset($data['output_text'])&&is_string($data['output_text']))return $data['output_text'];
    $text='';
    foreach(($data['output']??[]) as $item){
        foreach(($item['content']??[]) as $content){
            if(isset($content['text'])&&is_string($content['text']))$text.=$content['text'];
        }
    }
    return $text;
}
function ai_openai_usage(array $data):int{
    $u=$data['usage']??($data['response']['usage']??[]);
    return (int)($u['output_tokens']??$u['outputTokens']??0);
}
function aws_sign(string $method,string $url,array $headers,string $body,string $access,string $secret,string $region,string $service='bedrock') :array{
    $parts=parse_url($url);$host=$parts['host'];$path=$parts['path']??'/';$query=$parts['query']??'';$amz=date('Ymd\THis\Z');$date=substr($amz,0,8);
    $headers['host']=$host;$headers['x-amz-date']=$amz;ksort($headers);$canonHeaders='';foreach($headers as $k=>$v)$canonHeaders.=strtolower($k).':'.trim(preg_replace('/\s+/',' ',(string)$v))."\n";$signed=implode(';',array_keys($headers));$payloadHash=hash('sha256',$body);
    $canonical=$method."\n".$path."\n".$query."\n".$canonHeaders."\n".$signed."\n".$payloadHash;
    $scope="$date/$region/$service/aws4_request";$string="AWS4-HMAC-SHA256\n$amz\n$scope\n".hash('sha256',$canonical);$kDate=hash_hmac('sha256',$date,'AWS4'.$secret,true);$kRegion=hash_hmac('sha256',$region,$kDate,true);$kService=hash_hmac('sha256',$service,$kRegion,true);$kSigning=hash_hmac('sha256','aws4_request',$kService,true);$sig=hash_hmac('sha256',$string,$kSigning);
    $headers['Authorization']="AWS4-HMAC-SHA256 Credential=$access/$scope, SignedHeaders=$signed, Signature=$sig";return $headers;
}
function ai_uses_relay(array $cred):bool{
    $transport=(string)($cred['transport']??'relay');
    $relay=(string)($cred['relay_url']??'');
    // Stable production path: if a relay is configured, use it unless the
    // administrator/customer explicitly selected Direct to AWS.
    return $relay!=='' && $transport!=='direct';
}
function ai_relay_url(array $cred,string $url):string{
    if(!ai_uses_relay($cred))return $url;
    $relay=rtrim((string)$cred['relay_url'],'/');
    // Preserve the requested OpenAI-compatible API path so normal Chat
    // Completions calls are not accidentally sent to /responses.
    $path=parse_url($url,PHP_URL_PATH) ?: '/responses';
    $target=str_ends_with($path,'/chat/completions')?'/chat/completions':'/responses';
    $relay=preg_replace('~/(?:responses|chat/completions)$~','',$relay);
    return $relay.$target;
}
function ai_http(string $url,string $body,array $cred,bool $stream=false,?callable $onChunk=null):array{
    $usingRelay=ai_uses_relay($cred);
    if($usingRelay){
        $url=ai_relay_url($cred,$url);
        $headers=['content-type'=>'application/json','accept'=>$stream?'text/event-stream':'application/json'];
        if($cred['relay_token']!=='')$headers['x-pocketcat-relay-token']=$cred['relay_token'];
    }else{
        $headers=['content-type'=>'application/json','accept'=>$stream?'text/event-stream':'application/json'];
        if($cred['api_key']!=='')$headers['authorization']='Bearer '.$cred['api_key'];
        elseif($cred['access_key']!==''&&$cred['secret_key']!==''){
            if($cred['session_token']!=='')$headers['x-amz-security-token']=$cred['session_token'];
            $headers=aws_sign('POST',$url,$headers,$body,$cred['access_key'],$cred['secret_key'],$cred['region']);
        }else return [false,'Configure a Bedrock API key or AWS Access Key / Secret Key in Admin → AWS Bedrock.'];
    }
    if(!function_exists('curl_init'))return [false,'PHP cURL is not enabled on this server. Enable the cURL extension in hosting/PHP settings.'];
    $ch=curl_init($url);$out='';
    $opts=[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>!$stream,CURLOPT_HEADER=>false,CURLOPT_TIMEOUT=>90,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_HTTPHEADER=>array_map(fn($k)=>$k.': '.$headers[$k],array_keys($headers)),CURLOPT_USERAGENT=>'PocketCat-AI/1.0',CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
    curl_setopt_array($ch,$opts);
    if($stream){curl_setopt($ch,CURLOPT_WRITEFUNCTION,function($ch,$chunk)use(&$out,$onChunk){$out.=$chunk;if($onChunk)$onChunk($chunk);return strlen($chunk);});}
    $bodyOut=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$errno=curl_errno($ch);$err=curl_error($ch);curl_close($ch);
    if($bodyOut===false){
        $detail=$err!==''?$err:'Unknown cURL transport error';
        $parts=parse_url($url);$host=$parts['host']??'the relay/Bedrock endpoint';
        $resolved=$host!==''?gethostbyname($host):'';
        if($errno===6)$hint="DNS could not resolve $host.";
        elseif($errno===7)$hint=($resolved!==$host?"DNS resolved $host to $resolved, but the hosting server cannot establish an outbound TCP/HTTPS connection to $host port 443.":"The hosting server cannot establish an outbound TCP/HTTPS connection to $host port 443.");
        elseif(in_array($errno,[35,51,53,60],true))$hint="TLS/SSL negotiation failed between the hosting server and $host.";
        else $hint="Check outbound HTTPS, DNS, firewall and TLS support on the hosting server.";
        return [false,($usingRelay?'Relay':'Bedrock').' transport error (cURL #'.$errno.'): '.$detail.'. '.$hint];
    }
    if($http<200||$http>=300){
        $d=json_decode($out!==''?$out:$bodyOut,true);
        $message=$d['message']??$d['Message']??($d['error']['message']??'');
        if(is_array($message))$message=json_encode($message);
        $message=ai_safe_provider_error((string)$message);
        return [false,($usingRelay?'Relay HTTP ':'AI provider HTTP ').$http.': '.$message];
    }
    return [true,$out!==''?$out:$bodyOut];
}

function ai_extract_text(array $data):string{
    $chunks=$data['output']['message']['content']??[];$text='';foreach($chunks as $c)if(isset($c['text']))$text.=$c['text'];return $text;
}

function ai_responses_request(array $input,array $c,bool $agent=false):array{
    // Compatibility wrapper. Agent mode intentionally uses the Chat
    // Completions tool-calling contract because it is supported by the
    // OpenAI-compatible Bedrock path and by PocketCat relays.
    if($agent)return ai_chat_completions_agent_request($input,$c);
    $base=ai_openai_base_url($c);$url=rtrim($base,'/').'/responses';
    $body=['model'=>$c['model'],'input'=>$input,'store'=>false,'max_output_tokens'=>4096,'temperature'=>0.3,'stream'=>false];
    [$ok,$raw]=ai_http($url,json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$c,false);
    if(!$ok)return [false,$raw];$data=json_decode($raw,true);if(!is_array($data))return [false,'Bedrock returned invalid JSON.'];
    $out=ai_openai_extract_text($data);return $out!==''?[true,$out]:[false,'Bedrock returned no text.'];
}
function ai_chat_completions_agent_request(array $messages,array $c):array{
    $url=rtrim(ai_openai_base_url($c),'/').'/chat/completions';
    $tools=[];foreach(ai_agent_tools() as $tool){$tools[]=['type'=>'function','function'=>['name'=>$tool['name'],'description'=>$tool['description'],'parameters'=>$tool['parameters']]];}
    $working=$messages;
    for($round=0;$round<5;$round++){
        $payload=['model'=>$c['model'],'messages'=>$working,'tools'=>$tools,'tool_choice'=>'auto','max_completion_tokens'=>4096,'temperature'=>0.2,'stream'=>false];
        [$ok,$raw]=ai_http($url,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$c,false);if(!$ok)return [false,$raw];
        $data=json_decode($raw,true);if(!is_array($data))return [false,'Bedrock returned invalid JSON.'];
        $choice=$data['choices'][0]??[];$message=$choice['message']??[];$calls=$message['tool_calls']??[];
        if(!$calls){$content=$message['content']??$choice['text']??'';if(is_array($content)){$x='';foreach($content as $part)if(isset($part['text']))$x.=$part['text'];$content=$x;}$text=trim((string)$content);if($text!=='')return [true,$text];$reasoning=$message['reasoning_content']??$message['reasoning']??'';return [false,trim((string)$reasoning)!==''?'Bedrock returned reasoning but no final text. Increase the model output budget or verify the model configuration.':'Bedrock returned an empty response. Verify the model configuration.'];}
        $working[]=['role'=>'assistant','content'=>$message['content']??null,'tool_calls'=>$calls];
        foreach($calls as $call){$name=(string)($call['function']['name']??'');$args=(string)($call['function']['arguments']??'{}');$result=ai_execute_agent_tool($name,$args);$working[]=['role'=>'tool','tool_call_id'=>(string)($call['id']??''),'content'=>$result];}
    }
    return [false,'Agent reached the tool-call limit before producing a final answer.'];
}
function ai_converse_messages(array $input):array{
    $messages=[];
    foreach($input as $m){
        $role=(($m['role']??'user')==='assistant')?'assistant':'user';
        $content=$m['content']??'';
        $blocks=[];
        if(is_array($content)){
            foreach($content as $part){
                if(isset($part['text']))$blocks[]=['text'=>(string)$part['text']];
                $url=(string)($part['image_url']['url']??'');
                if($url!==''&&preg_match('~^data:image/(jpeg|png|gif|webp);base64,([A-Za-z0-9+/=]+)$~D',$url,$match)){
                    $bytes=base64_decode($match[2],true);
                    if($bytes!==false)$blocks[]=['image'=>['format'=>$match[1]==='jpeg'?'jpeg':$match[1],'source'=>['bytes'=>$bytes]]];
                }
            }
        }else{
            $blocks[]=['text'=>(string)$content];
        }
        if($blocks)$messages[]=['role'=>$role,'content'=>$blocks];
    }
    return $messages;
}
function ai_converse_request(array $input,array $c):array{
    $url=ai_url($c['region'],$c['model'],'converse',$c['endpoint']);$body=['messages'=>ai_converse_messages($input),'inferenceConfig'=>['maxTokens'=>4096,'temperature'=>0.2]];
    [$ok,$raw]=ai_http($url,json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$c,false);if(!$ok)return [false,$raw];$data=json_decode($raw,true);if(!is_array($data))return [false,'Bedrock returned invalid JSON.'];$text=trim(ai_extract_text($data));return $text!==''?[true,$text]:[false,'Bedrock returned no text. Verify the Bedrock model access, region, credentials, and model ID.'];
}
function ai_converse_agent_request(array $input,array $c):array{
    $url=ai_url($c['region'],$c['model'],'converse',$c['endpoint']);$toolSpecs=[];foreach(ai_agent_tools() as $tool)$toolSpecs[]=['toolSpec'=>['name'=>$tool['name'],'description'=>$tool['description'],'inputSchema'=>['json'=>$tool['parameters']]]];$messages=ai_converse_messages($input);
    for($round=0;$round<5;$round++){
        $body=['messages'=>$messages,'inferenceConfig'=>['maxTokens'=>4096,'temperature'=>0.2],'toolConfig'=>['tools'=>$toolSpecs]];[$ok,$raw]=ai_http($url,json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$c,false);if(!$ok)return [false,$raw];$data=json_decode($raw,true);if(!is_array($data))return [false,'Bedrock returned invalid JSON.'];$assistant=$data['output']['message']??[];$blocks=$assistant['content']??[];$uses=[];$text='';foreach($blocks as $block){if(isset($block['text']))$text.=$block['text'];if(isset($block['toolUse']))$uses[]=$block['toolUse'];}
        if(!$uses){$text=trim($text);return $text!==''?[true,$text]:[false,'Bedrock returned no text.'];}
        $messages[]=$assistant;$results=[];foreach($uses as $use){$name=(string)($use['name']??'');$result=ai_execute_agent_tool($name,json_encode($use['input']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$decoded=json_decode($result,true);$results[]=['toolResult'=>['toolUseId'=>(string)($use['toolUseId']??''),'content'=>[['json'=>is_array($decoded)?$decoded:['result'=>$result]]]]];}$messages[]=['role'=>'user','content'=>$results];
    }
    return [false,'Agent reached the tool-call limit before producing a final answer.'];
}
function ai_agent_tools():array{
    return [
        ['type'=>'function','name'=>'search_workspace','description'=>'Search the current user tasks, notes and projects by title or text.','parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string']],'required'=>['query']]],
        ['type'=>'function','name'=>'create_task','description'=>'Create one task for the current user. Use only when explicitly requested.','parameters'=>['type'=>'object','properties'=>['title'=>['type'=>'string'],'description'=>['type'=>'string'],'priority'=>['type'=>'string','enum'=>['Low','Medium','High']],'due_date'=>['type'=>'string']],'required'=>['title']]],
        ['type'=>'function','name'=>'create_note','description'=>'Create one note for the current user. Use only when explicitly requested.','parameters'=>['type'=>'object','properties'=>['title'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['title','content']]],
        ['type'=>'function','name'=>'create_project','description'=>'Create one project for the current user. Use only when explicitly requested.','parameters'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'description'=>['type'=>'string'],'status'=>['type'=>'string','enum'=>['Planning','Active','Completed','Archived']]],'required'=>['name']]],
        ['type'=>'function','name'=>'inspect_chat_attachment','description'=>'Read a supported text attachment or list the contents of a ZIP attached to the current user chat. Use when the user asks to inspect an attachment.','parameters'=>['type'=>'object','properties'=>['attachment_id'=>['type'=>'integer']],'required'=>['attachment_id']]],
        ['type'=>'function','name'=>'extract_chat_zip','description'=>'Safely extract a ZIP attached to the current user chat into the private, non-public AI workspace. Use only when requested.','parameters'=>['type'=>'object','properties'=>['attachment_id'=>['type'=>'integer']],'required'=>['attachment_id']]],
        ['type'=>'function','name'=>'read_extracted_file','description'=>'Read a supported text/source file from a ZIP already extracted from the current user chat.','parameters'=>['type'=>'object','properties'=>['attachment_id'=>['type'=>'integer'],'path'=>['type'=>'string']],'required'=>['attachment_id','path']]],
        ['type'=>'function','name'=>'save_extracted_file','description'=>'Create or update a text/source file inside the private extraction workspace for a chat ZIP. Use only when the user explicitly requests file changes.','parameters'=>['type'=>'object','properties'=>['attachment_id'=>['type'=>'integer'],'path'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['attachment_id','path','content']]],
        ['type'=>'function','name'=>'package_chat_zip','description'=>'Package the private extracted workspace for a chat ZIP into a downloadable archive. Use only when the user asks for a ZIP output.','parameters'=>['type'=>'object','properties'=>['attachment_id'=>['type'=>'integer']],'required'=>['attachment_id']]],
        ['type'=>'function','name'=>'read_workspace_file','description'=>'Read a text/source file from the private workspace for this chat or project.','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string']],'required'=>['path']]],
        ['type'=>'function','name'=>'write_workspace_file','description'=>'Create or update a text/source file in the private workspace for this chat or project. Use only when the user explicitly asks to change files.','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['path','content']]],
        ['type'=>'function','name'=>'delete_workspace_file','description'=>'Delete a file from the private workspace for this chat or project. Use only when the user explicitly requests deletion.','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string']],'required'=>['path']]],
        ['type'=>'function','name'=>'package_workspace_files','description'=>'Create a downloadable ZIP of the private workspace for this chat or project. Use only when requested.','parameters'=>['type'=>'object','properties'=>['scope'=>['type'=>'string','enum'=>['current_workspace']]],'required'=>['scope']]],
    ];
}
function ai_owned_chat_attachment(int $attachmentId,int $uid):?array{
    $s=db()->prepare('SELECT a.* FROM chat_attachments a JOIN conversations c ON c.id=a.conversation_id WHERE a.id=? AND a.user_id=? AND c.user_id=? LIMIT 1');
    $s->execute([$attachmentId,$uid,$uid]);
    $attachment=$s->fetch();
    if(!$attachment)return null;
    $publicPath=parse_url((string)($attachment['storage_path']??''),PHP_URL_PATH);
    if(!is_string($publicPath)||!preg_match('~^/uploads/media/'.preg_quote((string)$uid,'~').'/chat/[A-Za-z0-9._-]+$~D',$publicPath))return null;
    $root=realpath(__DIR__.'/../uploads/media/'.$uid.'/chat');
    $path=realpath(__DIR__.'/..'.$publicPath);
    if(!$root||!$path||!str_starts_with($path,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||!is_file($path))return null;
    $attachment['_real_path']=$path;
    return $attachment;
}
function ai_zip_member_is_safe(string $member):bool{
    $member=rtrim(str_replace('\\','/',$member),'/');
    if($member===''||str_starts_with($member,'/')||preg_match('/^[A-Za-z]:/',$member)||preg_match('/[\x00-\x1f*?\[\]]/',$member))return false;
    foreach(explode('/',$member) as $index=>$segment)if($segment==='..'||$segment===''||($index===0&&str_starts_with($segment,'-')))return false;
    return true;
}
function ai_zip_members(string $archive):array{
    if(!function_exists('proc_open'))throw new RuntimeException('ZIP inspection is unavailable on this server.');
    $process=proc_open(['zipinfo','-1',$archive],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('ZIP inspection could not start.');
    fclose($pipes[0]);
    $output=stream_get_contents($pipes[1],1024*1024);
    $error=stream_get_contents($pipes[2],8192);
    fclose($pipes[1]);fclose($pipes[2]);
    $status=proc_close($process);
    if($status!==0)throw new RuntimeException('The uploaded ZIP could not be read: '.trim($error));
    $members=array_values(array_filter(explode("\n",str_replace("\r",'',(string)$output)),static fn($name)=>$name!==''));
    if(count($members)>200)throw new RuntimeException('This archive contains too many entries (limit: 200).');
    foreach($members as $member)if(!ai_zip_member_is_safe($member))throw new RuntimeException('The archive contains an unsafe file path.');
    return $members;
}
function ai_zip_read_member(string $archive,string $member,int $maxBytes):string{
    $process=proc_open(['unzip','-p',$archive,$member],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('ZIP extraction could not start.');
    fclose($pipes[0]);
    $content=stream_get_contents($pipes[1],$maxBytes+1);
    if(strlen((string)$content)>$maxBytes)proc_terminate($process);
    $error=stream_get_contents($pipes[2],8192);
    fclose($pipes[1]);fclose($pipes[2]);
    $status=proc_close($process);
    if(strlen((string)$content)>$maxBytes)throw new RuntimeException('A ZIP entry exceeds the per-file extraction limit.');
    if($status!==0)throw new RuntimeException('A ZIP entry could not be read: '.trim($error));
    return (string)$content;
}
function ai_private_attachment_workspace(int $uid,int $attachmentId):string{
    return __DIR__.'/../storage/ai-workspaces/'.$uid.'/attachments/'.$attachmentId;
}
function ai_safe_workspace_file(string $relativePath):bool{
    if($relativePath===''||strlen($relativePath)>500||str_starts_with($relativePath,'/')||str_contains($relativePath,'\\')||preg_match('/[\x00-\x1f]/',$relativePath))return false;
    foreach(explode('/',$relativePath) as $segment)if($segment===''||$segment==='.'||$segment==='..'||str_starts_with($segment,'-'))return false;
    return true;
}
function ai_workspace_file_is_text(string $path):bool{
    return preg_match('/\.(?:txt|md|csv|json|xml|html|css|js|php|sql|yml|yaml|ini|log|py|go|rs|java|ts|tsx|jsx|sh)$/i',$path)===1;
}
function ai_agent_workspace_root(int $uid,int $conversationId,int $projectId=0):string{
    if($uid<=0||$conversationId<=0)throw new RuntimeException('Workspace context is unavailable.');
    $context=$GLOBALS['ai_agent_context']??[];
    if((int)($context['user_id']??0)!==$uid||(int)($context['conversation_id']??0)!==$conversationId)throw new RuntimeException('Workspace context is unavailable.');
    if($projectId>0){
        $s=db()->prepare('SELECT id FROM projects WHERE id=? AND user_id=?');
        $s->execute([$projectId,$uid]);
        if(!$s->fetchColumn())throw new RuntimeException('Project workspace not found.');
        $relative='projects/'.$projectId;
    }else{
        $relative='conversations/'.$conversationId;
    }
    $base=__DIR__.'/../storage/ai-workspaces/'.$uid.'/'.$relative;
    if(!is_dir($base)&&!mkdir($base,0700,true)&&!is_dir($base))throw new RuntimeException('The private AI workspace is not writable.');
    $root=realpath($base);
    $privateRoot=realpath(__DIR__.'/../storage/ai-workspaces/'.$uid);
    if(!$root||!$privateRoot||!str_starts_with($root,rtrim($privateRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))throw new RuntimeException('The private AI workspace is unavailable.');
    return $root;
}
function ai_extract_chat_zip(array $attachment,int $uid):array{
    $members=ai_zip_members($attachment['_real_path']);
    $destination=ai_private_attachment_workspace($uid,(int)$attachment['id']).'/extracted';
    if(!is_dir($destination)&&!mkdir($destination,0700,true)&&!is_dir($destination))throw new RuntimeException('The private AI workspace is not writable.');
    $root=realpath($destination);
    if(!$root)throw new RuntimeException('The private AI workspace could not be initialized.');
    $total=0;$extracted=[];$previews=[];
    foreach($members as $member){
        if(str_ends_with($member,'/'))continue;
        $relativePath=implode('/',array_values(array_filter(explode('/',str_replace('\\','/',$member)),static fn($part)=>$part!=='.')));
        if(!ai_safe_workspace_file($relativePath))throw new RuntimeException('The archive contains an unsafe file path.');
        $bytes=ai_zip_read_member($attachment['_real_path'],$member,2*1024*1024);
        $total+=strlen($bytes);
        if($total>20*1024*1024)throw new RuntimeException('The extracted archive exceeds the 20 MB total limit.');
        $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath);
        $parent=dirname($path);
        if(!is_dir($parent)&&!mkdir($parent,0700,true)&&!is_dir($parent))throw new RuntimeException('A private workspace folder could not be created.');
        $realParent=realpath($parent);
        if(!$realParent||($realParent!==$root&&!str_starts_with($realParent,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))throw new RuntimeException('The archive contains an unsafe destination.');
        if(file_put_contents($path,$bytes,LOCK_EX)===false)throw new RuntimeException('An archive file could not be saved.');
        @chmod($path,0600);
        $extracted[]=$relativePath;
        if(count($previews)<15&&ai_workspace_file_is_text($relativePath))$previews[$relativePath]=mb_substr($bytes,0,4000);
    }
    return ['destination'=>'private AI workspace for attachment '.(int)$attachment['id'],'extracted_count'=>count($extracted),'files'=>$extracted,'text_previews'=>$previews,'bytes_written'=>$total];
}
function ai_execute_agent_tool(string $name,string $arguments):string{
    $args=json_decode($arguments,true);if(!is_array($args))$args=[];$uid=(int)(current_user()['id']??0);if($uid<=0)return json_encode(['ok'=>false,'error'=>'Not authenticated.']);
    try{
        if($name==='search_workspace'){
            $q='%'.trim((string)($args['query']??'')).'%';$out=['tasks'=>[],'notes'=>[],'projects'=>[]];
            foreach(['tasks'=>"SELECT id,title,status,priority,due_date FROM tasks WHERE user_id=? AND (title LIKE ? OR description LIKE ?) ORDER BY updated_at DESC LIMIT 10",'notes'=>"SELECT id,title,updated_at FROM notes WHERE user_id=? AND (title LIKE ? OR content LIKE ?) ORDER BY updated_at DESC LIMIT 10",'projects'=>"SELECT id,name AS title,status,due_date FROM projects WHERE user_id=? AND (name LIKE ? OR description LIKE ?) ORDER BY updated_at DESC LIMIT 10"] as $k=>$sql){$s=db()->prepare($sql);$s->execute([$uid,$q,$q]);$out[$k]=$s->fetchAll();}return json_encode(['ok'=>true,'results'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        if($name==='create_task'){
            if(plan_limit_reached('tasks',$uid))return json_encode(['ok'=>false,'error'=>'Task plan limit reached.']);$title=trim((string)($args['title']??''));if($title==='')return json_encode(['ok'=>false,'error'=>'Task title is required.']);$priority=in_array(($args['priority']??'Medium'),['Low','Medium','High'],true)?$args['priority']:'Medium';$due=trim((string)($args['due_date']??''));if($due!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due))$due='';$projectId=(int)(($GLOBALS['ai_agent_context']['project_id']??0));if($projectId){$p=db()->prepare('SELECT id FROM projects WHERE id=? AND user_id=?');$p->execute([$projectId,$uid]);if(!$p->fetchColumn())return json_encode(['ok'=>false,'error'=>'Project not found.']);}db()->prepare("INSERT INTO tasks(project_id,user_id,title,description,status,priority,due_date,created_at,updated_at) VALUES(?,?,?,?,'Todo',?,?,NOW(),NOW())")->execute([$projectId?:null,$uid,mb_substr($title,0,220),mb_substr((string)($args['description']??''),0,5000),$priority,$due!==''?$due:null]);increment_counter('tasks_created_counter');return json_encode(['ok'=>true,'created'=>'task','id'=>(int)db()->lastInsertId(),'title'=>$title,'url'=>'/tasks/?project_id='.$projectId],JSON_UNESCAPED_UNICODE);
        }
        if($name==='create_note'){
            if(plan_limit_reached('notes',$uid))return json_encode(['ok'=>false,'error'=>'Note plan limit reached.']);$title=trim((string)($args['title']??''));$content=trim((string)($args['content']??''));if($title===''||$content==='')return json_encode(['ok'=>false,'error'=>'Note title and content are required.']);db()->prepare('INSERT INTO notes(user_id,title,content,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())')->execute([$uid,mb_substr($title,0,220),$content]);increment_counter('notes_created_counter');return json_encode(['ok'=>true,'created'=>'note','id'=>(int)db()->lastInsertId(),'title'=>$title],JSON_UNESCAPED_UNICODE);
        }
        if($name==='create_project'){
            if(plan_limit_reached('projects',$uid))return json_encode(['ok'=>false,'error'=>'Project plan limit reached.']);$name=trim((string)($args['name']??''));if($name==='')return json_encode(['ok'=>false,'error'=>'Project name is required.']);$status=in_array(($args['status']??'Planning'),['Planning','Active','Completed','Archived'],true)?$args['status']:'Planning';db()->prepare("INSERT INTO projects(user_id,name,description,status,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$uid,mb_substr($name,0,180),mb_substr((string)($args['description']??''),0,5000),$status]);$projectId=(int)db()->lastInsertId();db()->prepare('INSERT INTO ai_project_settings(project_id,user_id,custom_instructions,context_text,updated_at) VALUES(?,?,NULL,NULL,NOW())')->execute([$projectId,$uid]);increment_counter('projects_created_counter');return json_encode(['ok'=>true,'created'=>'project','id'=>$projectId,'name'=>$name,'url'=>'/projects/'],JSON_UNESCAPED_UNICODE);
        }
        if($name==='inspect_chat_attachment'||$name==='extract_chat_zip'){
            $attachmentId=(int)($args['attachment_id']??0);
            $attachment=ai_owned_chat_attachment($attachmentId,$uid);
            if(!$attachment)return json_encode(['ok'=>false,'error'=>'Attachment not found or not accessible.']);
            $isZip=strtolower((string)$attachment['kind'])==='archive'||str_ends_with(strtolower((string)$attachment['name']),'.zip');
            if(!$isZip){
                $mime=strtolower((string)$attachment['mime_type']);
                if(!str_starts_with($mime,'text/')&&!in_array($mime,['application/json','application/xml','application/javascript'],true))return json_encode(['ok'=>false,'error'=>'This attachment type is not available for text inspection.']);
                if((int)$attachment['size_bytes']>128*1024)return json_encode(['ok'=>false,'error'=>'Text inspection is limited to 128 KB per file.']);
                $content=file_get_contents($attachment['_real_path']);
                if($content===false)return json_encode(['ok'=>false,'error'=>'The attachment could not be read.']);
                return json_encode(['ok'=>true,'name'=>$attachment['name'],'mime'=>$mime,'content'=>$content],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            if($name==='inspect_chat_attachment'){
                $members=ai_zip_members($attachment['_real_path']);
                return json_encode(['ok'=>true,'name'=>$attachment['name'],'entries'=>$members,'entry_count'=>count($members)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $result=ai_extract_chat_zip($attachment,$uid);
            $result['download_urls']=[];
            foreach($result['files'] as $path)$result['download_urls'][$path]='/api/ai-workspace-file.php?attachment_id='.$attachmentId.'&path='.rawurlencode($path);
            return json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        if(in_array($name,['read_extracted_file','save_extracted_file','package_chat_zip'],true)){
            $attachmentId=(int)($args['attachment_id']??0);
            $attachment=ai_owned_chat_attachment($attachmentId,$uid);
            if(!$attachment||!(strtolower((string)$attachment['kind'])==='archive'||str_ends_with(strtolower((string)$attachment['name']),'.zip')))return json_encode(['ok'=>false,'error'=>'ZIP attachment not found or not accessible.']);
            $workspace=ai_private_attachment_workspace($uid,$attachmentId);
            $extractedRoot=realpath($workspace.'/extracted');
            if($name==='read_extracted_file'){
                $relativePath=(string)($args['path']??'');
                if(!ai_safe_workspace_file($relativePath)||!ai_workspace_file_is_text($relativePath)||!$extractedRoot)return json_encode(['ok'=>false,'error'=>'That text file is not available in the extracted workspace.']);
                $path=realpath($extractedRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath));
                if(!$path||!str_starts_with($path,rtrim($extractedRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>128*1024)return json_encode(['ok'=>false,'error'=>'The requested text file exceeds the read limit or is unavailable.']);
                $content=file_get_contents($path);
                return json_encode(['ok'=>true,'path'=>$relativePath,'content'=>$content===false?'':$content],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            if($name==='save_extracted_file'){
                $relativePath=(string)($args['path']??'');$content=(string)($args['content']??'');
                if(!ai_safe_workspace_file($relativePath)||!ai_workspace_file_is_text($relativePath)||strlen($content)>262144||!$extractedRoot)return json_encode(['ok'=>false,'error'=>'The requested file path, type, or content size is not allowed.']);
                $path=$extractedRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath);
                $parent=dirname($path);
                if(!is_dir($parent)&&!mkdir($parent,0700,true)&&!is_dir($parent))throw new RuntimeException('A private workspace folder could not be created.');
                $realParent=realpath($parent);
                if(!$realParent||($realParent!==$extractedRoot&&!str_starts_with($realParent,rtrim($extractedRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))throw new RuntimeException('The file destination is not safe.');
                if(file_put_contents($path,$content,LOCK_EX)===false)throw new RuntimeException('The workspace file could not be saved.');
                @chmod($path,0600);
                return json_encode(['ok'=>true,'path'=>$relativePath,'url'=>'/api/ai-workspace-file.php?attachment_id='.$attachmentId.'&path='.rawurlencode($relativePath)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            if(!$extractedRoot||!function_exists('proc_open'))return json_encode(['ok'=>false,'error'=>'ZIP packaging is unavailable on this server.']);
            $archive=$workspace.'/updated.zip';
            if(is_file($archive)&&!unlink($archive))throw new RuntimeException('The previous ZIP package could not be replaced.');
            $process=proc_open(['zip','-q','-r',$archive,'.'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$extractedRoot);
            if(!is_resource($process))return json_encode(['ok'=>false,'error'=>'ZIP packaging could not start.']);
            fclose($pipes[0]);$stdout=stream_get_contents($pipes[1],8192);$stderr=stream_get_contents($pipes[2],8192);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
            if($status!==0||!is_file($archive))return json_encode(['ok'=>false,'error'=>'ZIP packaging failed: '.trim($stderr?:$stdout)]);
            return json_encode(['ok'=>true,'url'=>'/api/ai-workspace-file.php?attachment_id='.$attachmentId.'&download=zip'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        if(in_array($name,['read_workspace_file','write_workspace_file','delete_workspace_file','package_workspace_files'],true)){
            $context=$GLOBALS['ai_agent_context']??[];
            $conversationId=(int)($context['conversation_id']??0);
            $projectId=(int)($context['project_id']??0);
            $root=ai_agent_workspace_root($uid,$conversationId,$projectId);
            $workspaceUrl='/api/ai-workspace-file.php?conversation_id='.$conversationId.'&project_id='.$projectId;
            if($name==='package_workspace_files'){
                if(!function_exists('proc_open'))return json_encode(['ok'=>false,'error'=>'ZIP packaging is unavailable on this server.']);
                $archive=$root.'.zip';
                if(is_file($archive)&&!unlink($archive))throw new RuntimeException('The previous ZIP package could not be replaced.');
                $process=proc_open(['zip','-q','-r',$archive,'.'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
                if(!is_resource($process))return json_encode(['ok'=>false,'error'=>'ZIP packaging could not start.']);
                fclose($pipes[0]);$stdout=stream_get_contents($pipes[1],8192);$stderr=stream_get_contents($pipes[2],8192);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
                if($status!==0||!is_file($archive))return json_encode(['ok'=>false,'error'=>'ZIP packaging failed: '.trim($stderr?:$stdout)]);
                return json_encode(['ok'=>true,'url'=>$workspaceUrl.'&download=zip'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $relativePath=(string)($args['path']??'');
            if(!ai_safe_workspace_file($relativePath)||!ai_workspace_file_is_text($relativePath))return json_encode(['ok'=>false,'error'=>'Only safe text/source file paths are allowed.']);
            $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath);
            if($name==='read_workspace_file'){
                $real=realpath($path);
                if(!$real||!str_starts_with($real,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||!is_file($real)||filesize($real)>128*1024)return json_encode(['ok'=>false,'error'=>'The requested file exceeds the read limit or is unavailable.']);
                $content=file_get_contents($real);
                return json_encode(['ok'=>true,'path'=>$relativePath,'content'=>$content===false?'':$content],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            if($name==='delete_workspace_file'){
                $real=realpath($path);
                if(!$real||!str_starts_with($real,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||!is_file($real)||!unlink($real))return json_encode(['ok'=>false,'error'=>'The requested file could not be deleted.']);
                return json_encode(['ok'=>true,'path'=>$relativePath,'deleted'=>true]);
            }
            $content=(string)($args['content']??'');
            if(strlen($content)>262144)return json_encode(['ok'=>false,'error'=>'The file exceeds the 256 KB limit.']);
            $used=0;
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $entry)if($entry->isFile())$used+=$entry->getSize();
            if($used+strlen($content)>20*1024*1024)return json_encode(['ok'=>false,'error'=>'The private workspace has reached its 20 MB limit.']);
            $parent=dirname($path);
            if(!is_dir($parent)&&!mkdir($parent,0700,true)&&!is_dir($parent))throw new RuntimeException('A private workspace folder could not be created.');
            $realParent=realpath($parent);
            if(!$realParent||($realParent!==$root&&!str_starts_with($realParent,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))throw new RuntimeException('The file destination is not safe.');
            if(file_put_contents($path,$content,LOCK_EX)===false)throw new RuntimeException('The workspace file could not be saved.');
            @chmod($path,0600);
            return json_encode(['ok'=>true,'path'=>$relativePath,'url'=>$workspaceUrl.'&path='.rawurlencode($relativePath)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    }catch(Throwable $e){error_log('[PocketCat AI agent tool] '.$e);return json_encode(['ok'=>false,'error'=>'The requested workspace action could not be completed.'],JSON_UNESCAPED_UNICODE);}
    return json_encode(['ok'=>false,'error'=>'Unknown tool.']);
}
function ai_chat_completions_request(array $messages,array $c,int $maxTokens=4096):array{
    $base=rtrim(ai_openai_base_url($c),'/');
    $url=$base.'/chat/completions';
    // AWS documents max_completion_tokens as an OpenAI field, but its
    // compatibility layer also maps it to the native maxTokens field. Keep
    // the request conservative and use max_tokens for broad compatibility.
    $payload=['model'=>$c['model'],'messages'=>$messages,'max_completion_tokens'=>$maxTokens,'temperature'=>0.2,'stream'=>false];
    [$ok,$raw]=ai_http($url,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$c,false);if(!$ok)return [false,$raw];
    $data=json_decode($raw,true);if(!is_array($data))return [false,'Bedrock returned invalid JSON.'];
    $choice=$data['choices'][0]??[];
    $content=$choice['message']['content']??$choice['text']??'';
    if(is_array($content)){ $x='';foreach($content as $part){if(isset($part['text']))$x.=$part['text'];elseif(isset($part['content']))$x.=(string)$part['content'];}$content=$x; }
    $text=trim((string)$content);
    if($text!=='')return [true,$text];
    $reasoning=$choice['message']['reasoning_content']??$choice['message']['reasoning']??'';
    if(is_array($reasoning)){ $x='';foreach($reasoning as $part)if(isset($part['text']))$x.=$part['text'];$reasoning=$x; }
    if(trim((string)$reasoning)!=='')return [false,'Bedrock returned reasoning but no final text. Increase the model output budget or verify the model configuration.'];
    $finish=(string)($choice['finish_reason']??'unknown');
    return [false,'Bedrock returned an empty response (finish_reason: '.$finish.'). Verify the Bedrock model access, API key permissions, region, and model ID.'];
}
function ai_chat(string $prompt,?string $model=null):array{
    $prompt=trim($prompt);if($prompt==='')return [false,'Enter a prompt first.'];$u=current_user();if(!$u)return [false,'Please log in to use the AI assistant.','LOGIN_REQUIRED'];
    if(!ai_is_premium($u)&&ai_seconds_remaining((int)$u['id'],$u)<0.001)return [false,'Your daily AI active-time allowance is exhausted. Please upgrade your plan.','LIMIT_REACHED'];
    $c=ai_credentials((int)$u['id']);
    $model=trim((string)$model);
    if($model==='' )$model=ai_user_model_preference((int)$u['id']);
    if($model!==''&&ai_valid_model_id($model))$c['model']=$model;
    if(!ai_uses_relay($c)&&!$c['api_key']&&(!$c['access_key']||!$c['secret_key']))return [false,'Amazon Bedrock credentials are not configured. Add your Bedrock settings in My Settings → AWS Bedrock, or use the administrator default.'];
    $started=microtime(true);
    $maxTokens=ai_is_premium($u)?4096:4096;
    $messages=[['role'=>'user','content'=>$prompt]];
    if(ai_openai_compatible($c)){
        [$ok,$text]=ai_chat_completions_request($messages,$c,$maxTokens);
    }else{
        [$ok,$text]=ai_converse_request($messages,$c);
    }
    if(!$ok)return [false,$text];
    ai_add_active_time((int)$u['id'],microtime(true)-$started,$u);log_event('ai_request',(int)$u['id']);return [true,$text];
}
function ai_stream(string $prompt):void{
    $u=current_user();$remaining=ai_seconds_remaining((int)$u['id'],$u);if(!ai_is_premium($u)&&$remaining<0.001){http_response_code(429);echo 'data: '.json_encode(['error'=>'Your daily AI active-time allowance is exhausted. Please upgrade your plan.'])."\n\n";return;}
    $started=microtime(true);
    $c=ai_credentials();if(!ai_uses_relay($c)&&!$c['api_key']&&(!$c['access_key']||!$c['secret_key'])){http_response_code(503);echo 'data: '.json_encode(['error'=>'Amazon Bedrock credentials are not configured.'])."\n\n";return;}    $maxTokens=ai_is_premium($u)?1200:900;
    header('Content-Type: text/event-stream');header('Cache-Control: no-cache');header('X-Accel-Buffering: no');
    $assembled='';$actualOutputTokens=0;
    if(ai_openai_compatible($c)){
        $payload=json_encode(['model'=>$c['model'],'messages'=>[['role'=>'user','content'=>trim($prompt)]],'max_completion_tokens'=>$maxTokens,'temperature'=>0.3,'stream'=>true],JSON_UNESCAPED_SLASHES);
        $url=rtrim(ai_openai_base_url($c),'/').'/chat/completions';$buffer='';
        [$ok,$raw]=ai_http($url,$payload,$c,true,function($chunk)use(&$assembled,&$actualOutputTokens,&$buffer){
            $buffer.=$chunk;
            while(($sep=strpos($buffer,"\n\n"))!==false){
                $event=substr($buffer,0,$sep);$buffer=substr($buffer,$sep+2);
                foreach(preg_split('/\r?\n/',$event) as $line){
                    if(strncmp($line,'data:',5)!==0)continue;
                    $json=trim(substr($line,5));if($json===''||$json==='[DONE]')continue;$j=json_decode($json,true);if(!is_array($j))continue;
                    if(($j['type']??'')==='error'){echo 'data: '.json_encode(['error'=>$j['message']??'Bedrock Responses API error'],JSON_UNESCAPED_UNICODE)."\n\n";continue;}
                    $delta='';
                    if(isset($j['choices'][0]['delta']['content']))$delta=(string)$j['choices'][0]['delta']['content'];
                    elseif(($j['type']??'')==='response.output_text.delta')$delta=(string)($j['delta']??'');
                    if($delta!==''){$assembled.=$delta;echo 'data: '.json_encode(['delta'=>$delta],JSON_UNESCAPED_UNICODE)."\n\n";@ob_flush();@flush();}
                    if(isset($j['usage']))$actualOutputTokens=ai_openai_usage($j);
                }
            }
        });
        if(!$ok){echo 'data: '.json_encode(['error'=>$raw],JSON_UNESCAPED_UNICODE)."\n\n";return;}
        if($assembled!=='')ai_add_active_time((int)$u['id'],microtime(true)-$started,$u);echo 'data: '.json_encode(['done'=>true])."\n\n";return;
    }
    $payload=json_encode(['messages'=>[['role'=>'user','content'=>[['text'=>trim($prompt)]]]],'inferenceConfig'=>['maxTokens'=>$maxTokens,'temperature'=>0.3]],JSON_UNESCAPED_SLASHES);$url=ai_url($c['region'],$c['model'],'converse-stream',$c['endpoint']);$buffer='';
    [$ok,$raw]=ai_http($url,$payload,$c,true,function($chunk)use(&$assembled,&$actualOutputTokens,&$buffer){
        $buffer.=$chunk;$pos=0;$len=strlen($buffer);
        while($pos+12<=$len){$total=unpack('N',substr($buffer,$pos,4))[1]??0;if($total<16)break;if($pos+$total>$len)break;$headerLen=unpack('N',substr($buffer,$pos+4,4))[1]??0;$payloadLen=$total-$headerLen-16;if($payloadLen<0)break;$payload=substr($buffer,$pos+12+$headerLen,$payloadLen);$j=json_decode($payload,true);if(is_array($j)){ $actualOutputTokens=(int)($j['metadata']['usage']['outputTokens']??$j['usage']['outputTokens']??$actualOutputTokens); $delta=$j['contentBlockDelta']['delta']['text']??$j['delta']['text']??'';if($delta!==''){ $assembled.=$delta;echo 'data: '.json_encode(['delta'=>$delta],JSON_UNESCAPED_UNICODE)."\n\n";@ob_flush();@flush(); }}$pos+=$total;}
        if($pos>0)$buffer=substr($buffer,$pos);
    });
    if(!$ok){echo 'data: '.json_encode(['error'=>$raw],JSON_UNESCAPED_UNICODE)."\n\n";}else{if($assembled!=='')ai_add_active_time((int)$u['id'],microtime(true)-$started,$u);echo 'data: '.json_encode(['done'=>true])."\n\n";}
}
