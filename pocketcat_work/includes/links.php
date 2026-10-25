<?php
declare(strict_types=1);

function link_slug(int $len=7):string{
    $alphabet='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    do{$s='';for($i=0;$i<$len;$i++)$s.=$alphabet[random_int(0,strlen($alphabet)-1)];$q=db()->prepare('SELECT 1 FROM short_links WHERE slug=? LIMIT 1');$q->execute([$s]);}while($q->fetchColumn());
    return $s;
}
function normalize_destination(string $url):string{
    $url=trim($url); if($url==='')throw new InvalidArgumentException('Destination URL is required.');
    if(!preg_match('~^https?://~i',$url))$url='https://'.$url;
    if(!filter_var($url,FILTER_VALIDATE_URL))throw new InvalidArgumentException('Enter a valid HTTP(S) URL.');
    $p=parse_url($url);$scheme=strtolower((string)($p['scheme']??''));
    if(!in_array($scheme,['http','https'],true))throw new InvalidArgumentException('Only HTTP and HTTPS destinations are allowed.');
    return $url;
}
function detect_device(string $ua):array{
    $ua=strtolower($ua);$os='Other';
    if(strpos($ua,'iphone')!==false||strpos($ua,'ipad')!==false||strpos($ua,'ipod')!==false)$os='iOS';
    elseif(strpos($ua,'android')!==false)$os='Android';
    elseif(strpos($ua,'windows')!==false)$os='Windows';
    elseif(strpos($ua,'mac os')!==false||strpos($ua,'macintosh')!==false)$os='macOS';
    elseif(strpos($ua,'linux')!==false)$os='Linux';
    $device=(strpos($ua,'mobile')!==false||strpos($ua,'iphone')!==false||strpos($ua,'android')!==false)?'Mobile':((strpos($ua,'tablet')!==false||strpos($ua,'ipad')!==false)?'Tablet':'Desktop');
    $browser='Other';
    foreach(['Edg'=>'Edge','OPR'=>'Opera','Chrome'=>'Chrome','Firefox'=>'Firefox','Safari'=>'Safari','MSIE'=>'Internet Explorer'] as $needle=>$name)if(strpos($ua,strtolower($needle))!==false){$browser=$name;break;}
    return [$os,$device,$browser];
}
function is_bot_ua(string $ua):bool{
    return (bool)preg_match('~bot|crawler|spider|slurp|bingpreview|facebookexternalhit|facebot|twitterbot|linkedinbot|pinterest|slackbot|discordbot|telegrambot|whatsapp|google-structured-data|headless|lighthouse|curl|wget|python-requests|httpclient~i',$ua);
}
function visitor_hash(string $ip,string $ua):string{return hash_hmac('sha256',$ip.'|'.$ua.'|'.gmdate('Y-m-d'),app_secret());}
function link_base_url():string{return rtrim((string)(app_config()['app_url']??'https://pocketcat.online'),'/');}
function link_public_url(string $slug):string{return link_base_url().'/r/'.rawurlencode($slug);}
function user_can_link(int $uid,int $linkUid):bool{return $uid===$linkUid || ((current_user()['role']??'')==='admin'&&is_admin_view());}
function link_is_expired(array $l):bool{
    if(!empty($l['expires_at'])&&strtotime((string)$l['expires_at'])<=time())return true;
    if(!empty($l['max_clicks'])&&(int)$l['max_clicks']>0&&(int)($l['clicks']??0)>=(int)$l['max_clicks'])return true;
    return false;
}
function link_allowed_by_rules(array $l,string $country,string $city,string $os):bool{
    if(!empty($l['allowed_countries'])){ $a=json_decode((string)$l['allowed_countries'],true);if(is_array($a)&&$a&& !in_array(strtoupper($country),array_map('strtoupper',$a),true))return false; }
    if(!empty($l['blocked_countries'])){ $a=json_decode((string)$l['blocked_countries'],true);if(is_array($a)&&in_array(strtoupper($country),array_map('strtoupper',$a),true))return false; }
    return true;
}
function link_pick_destination(array $l,string $os,string $country,string $city):string{
    $rules=json_decode((string)($l['routing_rules']??''),true);$rules=is_array($rules)?$rules:[];
    $candidates=[];
    if($os && isset($rules['os'][$os]))$candidates[]=$rules['os'][$os];
    if($country && isset($rules['country'][strtoupper($country)]))$candidates[]=$rules['country'][strtoupper($country)];
    if($city && isset($rules['city'][$city]))$candidates[]=$rules['city'][$city];
    foreach($candidates as $u){try{return normalize_destination((string)$u);}catch(Throwable $e){}}
    return (string)$l['destination_url'];
}
function threat_check_url(string $url):array{
    $key=secret_setting('safe_browsing_api_key','');
    if($key==='')return ['ok'=>true,'status'=>'not_configured','message'=>'Threat intelligence is not configured.'];
    $endpoint='https://safebrowsing.googleapis.com/v4/threatMatches:find?key='.rawurlencode($key);
    $payload=json_encode(['client'=>['clientId'=>'pocketcat','clientVersion'=>'1.0'],'threatInfo'=>['threatTypes'=>['MALWARE','SOCIAL_ENGINEERING','UNWANTED_SOFTWARE','POTENTIALLY_HARMFUL_APPLICATION'],'platformTypes'=>['ANY_PLATFORM'],'threatEntryTypes'=>['URL'],'threatEntries'=>[['url'=>$url]]]],JSON_UNESCAPED_SLASHES);
    $ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT_MS=>500,CURLOPT_TIMEOUT_MS=>1200]);$body=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
    if($body===false)return ['ok'=>true,'status'=>'unreachable','message'=>$err?:'Threat service unavailable; fail-open policy applied.'];
    $data=json_decode($body,true);if(isset($data['matches'])&&$data['matches'])return ['ok'=>false,'status'=>'blocked','message'=>'Destination was flagged by threat intelligence.'];
    return ['ok'=>true,'status'=>'clean','message'=>'Destination passed the threat check.'];
}
function link_rate_limit(string $bucket,int $limit=180):bool{
    $key=hash('sha256',$bucket);$now=time();
    if(function_exists('apcu_fetch')){ $k='pc_rl_'.$key;$v=apcu_fetch($k,$ok);if(!$ok)$v=['n'=>0,'t'=>$now];if($now-(int)$v['t']>=60)$v=['n'=>0,'t'=>$now];$v['n']++;apcu_store($k,$v,60);return $v['n']<=$limit; }
    try{$s=db()->prepare('INSERT INTO link_rate_limits(bucket,window_start,hits) VALUES(?,?,1) ON DUPLICATE KEY UPDATE hits=IF(window_start<? ,1,hits+1),window_start=IF(window_start<? ,VALUES(window_start),window_start)');$m=date('Y-m-d H:i:00',$now);$s->execute([$key,$m,$m,$m]);$q=db()->prepare('SELECT hits FROM link_rate_limits WHERE bucket=?');$q->execute([$key]);return (int)$q->fetchColumn()<=$limit;}catch(Throwable $e){return true;}
}
function link_webhook_event(int $linkId,array $event,?callable $transport=null):void{
    try{
        $s=db()->prepare('SELECT id,url,secret FROM link_webhooks WHERE link_id=? AND active=1');$s->execute([$linkId]);$hooks=$s->fetchAll();
        $eventName=(string)($event['event']??'event');
        foreach($hooks as $h){
            if(!webhook_target_is_safe((string)$h['url'])) { error_log('[PocketCat webhook] blocked unsafe target for webhook '.(int)$h['id']); continue; }
            $payload=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $sig=hash_hmac('sha256',$payload,(string)$h['secret']);
            $headers=['Content-Type: application/json','X-PocketCat-Signature: '.$sig];
            if($transport!==null){
                if(PHP_SAPI!=='cli')throw new RuntimeException('Mock webhook transport is available only to CLI tests.');
                $response=$transport('POST',(string)$h['url'],$headers,$payload);
                if(!is_array($response)||!isset($response[0],$response[1],$response[2])||!is_numeric($response[0])||!is_string($response[1])||!is_string($response[2]))throw new RuntimeException('Webhook mock transport returned an invalid response.');
                [$status,$responseBody,$responseHeaders]=[(int)$response[0],$response[1],$response[2]];
            }else{
                $ch=curl_init($h['url']);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CONNECTTIMEOUT_MS=>500,CURLOPT_TIMEOUT_MS=>2000,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]);
                $raw=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
                $responseHeaders=substr($raw,0,$headerSize);$responseBody=substr($raw,$headerSize);
            }
            db()->prepare('INSERT INTO link_webhook_deliveries(webhook_id,event_name,status_code,response_body,request_body,request_headers,response_headers,attempt_count,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')->execute([(int)$h['id'],$eventName,$status,substr($responseBody,0,10000),$payload,implode("\n",$headers),substr($responseHeaders,0,10000),1]);
        }
    }catch(Throwable $e){}
}
function link_user_agent_details(string $ua):array{
    $browser='Other';$browserVersion='';$engine='';
    if(preg_match('/Edg(?:e|A)?\/([0-9.]+)/i',$ua,$m)){$browser='Edge';$browserVersion=$m[1];$engine='Blink';}
    elseif(preg_match('/OPR\/([0-9.]+)/i',$ua,$m)){$browser='Opera';$browserVersion=$m[1];$engine='Blink';}
    elseif(preg_match('/Chrome\/([0-9.]+)/i',$ua,$m)){$browser='Chrome';$browserVersion=$m[1];$engine='Blink';}
    elseif(preg_match('/Firefox\/([0-9.]+)/i',$ua,$m)){$browser='Firefox';$browserVersion=$m[1];$engine='Gecko';}
    elseif(preg_match('/Version\/([0-9.]+).*Safari\//i',$ua,$m)){$browser='Safari';$browserVersion=$m[1];$engine='WebKit';}
    elseif(preg_match('/MSIE\s([0-9.]+)/i',$ua,$m)){$browser='Internet Explorer';$browserVersion=$m[1];$engine='Trident';}
    $os='Other';$osVersion='';
    if(preg_match('/(?:iPhone|CPU iPhone OS|iPad; CPU OS)\s*([0-9_]+)/i',$ua,$m)){$os='iOS';$osVersion=str_replace('_','.',$m[1]);}
    elseif(preg_match('/Android\s([0-9.]+)/i',$ua,$m)){$os='Android';$osVersion=$m[1];}
    elseif(preg_match('/Windows NT\s([0-9.]+)/i',$ua,$m)){$os='Windows';$osVersion=$m[1];}
    elseif(preg_match('/Mac OS X\s*([0-9_\.]+)/i',$ua,$m)){$os='macOS';$osVersion=str_replace('_','.',$m[1]);}
    elseif(stripos($ua,'Linux')!==false)$os='Linux';
    $device=(stripos($ua,'iPad')!==false||stripos($ua,'Tablet')!==false)?'Tablet':((stripos($ua,'Mobile')!==false||stripos($ua,'iPhone')!==false||stripos($ua,'Android')!==false)?'Mobile':'Desktop');
    $brand='';$model='';
    if(stripos($ua,'iPhone')!==false){$brand='Apple';$model='iPhone';}
    elseif(stripos($ua,'iPad')!==false){$brand='Apple';$model='iPad';}
    elseif(preg_match('/\b(SM-[A-Z0-9-]+)\b/i',$ua,$m)){$brand='Samsung';$model=strtoupper($m[1]);}
    elseif(preg_match('/\b(Pixel\s+[A-Za-z0-9 ]+)/i',$ua,$m)){$brand='Google';$model=trim($m[1]);}
    return compact('browser','browserVersion','engine','os','osVersion','device','brand','model');
}
function link_bot_type(string $ua):string{
    $map=[
      'whatsapp'=>'WhatsApp','telegrambot'=>'Telegram','discordbot'=>'Discord','slackbot'=>'Slack','facebookexternalhit'=>'Facebook','facebot'=>'Facebook','twitterbot'=>'X/Twitter','linkedinbot'=>'LinkedIn','bingbot'=>'Bing','googlebot'=>'Google','bingpreview'=>'Bing Preview','pinterest'=>'Pinterest','headless'=>'Headless browser','lighthouse'=>'Lighthouse','curl'=>'cURL','wget'=>'Wget','python-requests'=>'Python Requests'
    ];
    foreach($map as $needle=>$name)if(stripos($ua,$needle)!==false)return $name;
    return is_bot_ua($ua)?'Automated crawler':'';
}
function enqueue_link_click(array $l,string $destination,?array $geo=null):void{
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000);$ip=client_ip();$d=link_user_agent_details($ua);$bot=is_bot_ua($ua)?1:0;$botType=$bot?link_bot_type($ua):'';
    $ref=substr((string)($_SERVER['HTTP_REFERER']??''),0,500);$vh=visitor_hash($ip,$ua);$now=new DateTimeImmutable('now',new DateTimeZone(date_default_timezone_get()));$utc=$now->setTimezone(new DateTimeZone('UTC'));
    if($geo===null)$geo=geo_lookup($ip)?:[];
    $utm=[];foreach(['utm_source','utm_medium','utm_campaign','utm_term','utm_content'] as $k)$utm[$k]=trim((string)($_GET[$k]??''));
    $cfCountry=strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY']??''),0,2));$cfCity=substr((string)($_SERVER['HTTP_CF_CITY']??$_SERVER['HTTP_CF_IPCITY']??''),0,120);
    $country=(string)($geo['country_code']??$cfCountry);$city=(string)($geo['city']??$cfCity);
    $payload=['link_id'=>(int)$l['id'],'destination'=>$destination,'ip'=>$ip,'ip_hash'=>hash_hmac('sha256',$ip,app_secret()),'visitor_hash'=>$vh,'referrer'=>$ref,'browser'=>$d['browser'],'browser_version'=>$d['browserVersion'],'browser_engine'=>$d['engine'],'os'=>$d['os'],'os_version'=>$d['osVersion'],'device_category'=>$d['device'],'device_brand'=>$d['brand'],'device_model'=>$d['model'],'country_code'=>$country,'country_name'=>(string)($geo['country_name']??''),'region'=>(string)($geo['region']??''),'city'=>$city,'postal_code'=>(string)($geo['postal_code']??''),'latitude'=>$geo['latitude']??null,'longitude'=>$geo['longitude']??null,'timezone_name'=>(string)($geo['timezone']??''),'isp'=>(string)($geo['isp']??''),'asn'=>(string)($geo['asn']??''),'accept_language'=>substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??''),0,120),'utm_source'=>$utm['utm_source'],'utm_medium'=>$utm['utm_medium'],'utm_campaign'=>$utm['utm_campaign'],'utm_term'=>$utm['utm_term'],'utm_content'=>$utm['utm_content'],'is_bot'=>$bot,'bot_type'=>$botType,'user_agent'=>$ua,'clicked_at'=>$now->format('Y-m-d H:i:s'),'clicked_at_utc'=>$utc->format('Y-m-d H:i:s')];
    // Link analytics must be visible immediately. The previous implementation only queued
    // clicks for the cron worker, so installations without a running cron showed 0 clicks
    // forever. GeoIP is already local, so record the event synchronously and let the worker
    // remain available for legacy queued rows.
    try{
        db()->prepare('INSERT INTO link_clicks(link_id,visitor_hash,ip_hash,ip_address,referrer,browser,browser_version,browser_engine,os,os_version,device_category,device_brand,device_model,country_code,country_name,region,city,postal_code,latitude,longitude,timezone_name,isp,asn,accept_language,utm_source,utm_medium,utm_campaign,utm_term,utm_content,is_bot,bot_type,user_agent,clicked_at,clicked_at_utc) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
            (int)$l['id'],$vh,$payload['ip_hash'],$ip,$ref,$d['browser'],$d['browserVersion'],$d['engine'],$d['os'],$d['osVersion'],$d['device'],$d['brand'],$d['model'],$country,$payload['country_name'],$payload['region'],$city,$payload['postal_code'],$payload['latitude'],$payload['longitude'],$payload['timezone_name'],$payload['isp'],$payload['asn'],$payload['accept_language'],$utm['utm_source'],$utm['utm_medium'],$utm['utm_campaign'],$utm['utm_term'],$utm['utm_content'],$bot,$botType,$ua,$payload['clicked_at'],$payload['clicked_at_utc']
        ]);
        if(!$bot)db()->prepare('UPDATE short_links SET clicks=clicks+1,last_clicked_at=NOW() WHERE id=?')->execute([(int)$l['id']]);
    }catch(Throwable $ignored){}
}
function record_link_click(array $l,string $destination,?array $geo=null):void{enqueue_link_click($l,$destination,$geo);}
function parse_csv_rows(string $tmp):array{
    $out=[];$fh=fopen($tmp,'rb');if(!$fh)return $out;$header=fgetcsv($fh);if(!$header){fclose($fh);return $out;}$header=array_map(fn($x)=>strtolower(trim((string)$x)),$header);while(($row=fgetcsv($fh))!==false){$r=[];foreach($header as $i=>$h)$r[$h]=trim((string)($row[$i]??''));if(($r['destination_url']??$r['url']??'')!=='')$out[]=$r;}fclose($fh);return $out;
}
