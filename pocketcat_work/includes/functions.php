<?php
function app_config():array{
    static $c=null;
    if($c===null){
        $c=require __DIR__.'/../config.php';
        $f=__DIR__.'/../storage/app-config.php';
        if(is_file($f)){
            $saved=require $f;
            if(is_array($saved)) $c=array_replace_recursive($c,$saved);
        }
    }
    return $c;
}
function is_installed():bool{return is_file(__DIR__.'/../storage/installed.lock');}
function demo_growth_config():array{
    $base=setting('growth_base','10000');
    $base=is_numeric($base)?max(0,(float)$base):10000.0;
    $raw=trim((string)setting('growth_rates','5,10,15,25,10'));
    $rates=[];
    foreach(explode(',',$raw) as $part){
        $part=trim($part);
        if($part===''||!is_numeric($part))continue;
        $rates[]=max(0,min(100,(float)$part))/100;
    }
    if(!$rates)$rates=[.05,.10,.15,.25,.10];
    while(count($rates)<5)$rates[]=end($rates)?:.10;
    $rates=array_slice($rates,0,5);
    $rateLabels=array_map(static fn($rate)=>rtrim(rtrim(number_format($rate*100,2,'.',''),'0'),'.').'%', $rates);
    $defaultDescription='Presentation-only sample starting at '.number_format($base,0,'.',',').' users with '.implode(', ',$rateLabels).' monthly increases.';
    return [
        'title'=>(string)setting('growth_chart_title','6-month user growth demo'),
        'description'=>(string)setting('growth_chart_description',$defaultDescription),
        'show_badge'=>true,
        'show_chart'=>setting('show_demo_growth_chart','1')==='1',
        'base'=>$base,
        'rates'=>$rates,
    ];
}
function demo_growth_series():array{
    $config=demo_growth_config(); $value=$config['base']; $rates=$config['rates']; $out=[];
    for($i=5;$i>=0;$i--){
        $dt=new DateTime('first day of -'.$i.' month');
        $out[]=['label'=>$dt->format('M'),'value'=>(int)round($value)];
        if($i>0)$value*=1+($rates[5-$i]??end($rates)??.10);
    }
    if(setting('latest_month_linked','1')==='1' && $out){$stats=platform_stats();$out[count($out)-1]['value']=(int)$stats['latest_month_users'];}
    return $out;
}
function redirect(string $p):never{header('Location: '.$p);exit;}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function render_http_error_page(int $status,string $title,string $message):void{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' — PocketCat</title><style>body{margin:0;background:#f6f8fc;color:#172033;font:16px/1.6 system-ui,sans-serif}.error-page{box-sizing:border-box;max-width:680px;margin:12vh auto;padding:32px;background:#fff;border:1px solid #dbe3ee;border-radius:16px}.error-page h1{margin-top:0}.error-page a{color:#174ea6;font-weight:700}</style></head><body><main class="error-page"><h1>'.e($title).'</h1><p>'.e($message).'</p><a href="/">Return to PocketCat</a></main></body></html>';
}
function post(string $k,$d=null){return $_POST[$k]??$d;}
function getv(string $k,$d=null){return $_GET[$k]??$d;}
function valid_email(string $x):bool{return filter_var($x,FILTER_VALIDATE_EMAIL)!==false;}
function flash(string $t,string $m):void{$_SESSION['_flash'][]=[$t,$m];}
function flashes():array{$x=$_SESSION['_flash']??[];unset($_SESSION['_flash']);return $x;}
function client_ip():string{
    $cf=trim((string)($_SERVER['HTTP_CF_CONNECTING_IP']??''));
    if(filter_var($cf,FILTER_VALIDATE_IP))return $cf;
    $xff=(string)($_SERVER['HTTP_X_FORWARDED_FOR']??'');
    if($xff!==''){foreach(explode(',',$xff) as $candidate){$candidate=trim($candidate);if(filter_var($candidate,FILTER_VALIDATE_IP))return $candidate;}}
    $real=trim((string)($_SERVER['HTTP_X_REAL_IP']??''));
    if(filter_var($real,FILTER_VALIDATE_IP))return $real;
    return substr($_SERVER['REMOTE_ADDR']??'0.0.0.0',0,45);
}
function log_event(string $event,?int $uid=null):void{try{$s=db()->prepare('INSERT INTO activity_logs(user_id,event_type,ip_address,created_at) VALUES(?,?,?,NOW())');$s->execute([$uid,$event,client_ip()]);}catch(Throwable $e){}}
function setting(string $k,?string $d=null):?string{try{$s=db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');$s->execute([$k]);$v=$s->fetchColumn();return $v===false?$d:(string)$v;}catch(Throwable $e){return $d;}}
/** Validate webhook destinations defensively to reduce SSRF exposure. HTTPS/public DNS only. */
function webhook_target_is_safe(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts=parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme']??''))!=='https') return false;
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return false;
    if (isset($parts['port']) && (int)$parts['port']!==443) return false;
    $host=strtolower(rtrim((string)($parts['host']??''),'.'));
    if ($host==='' || $host==='localhost' || str_ends_with($host,'.localhost') || str_ends_with($host,'.local') || str_ends_with($host,'.internal')) return false;
    $ips=[];
    if (filter_var($host,FILTER_VALIDATE_IP)) $ips[]=$host;
    else {
        if (!function_exists('dns_get_record')) return false;
        $records=@dns_get_record($host, DNS_A|DNS_AAAA);
        if (!is_array($records) || !$records) return false;
        foreach ($records as $record) {
            if (!empty($record['ip'])) $ips[]=(string)$record['ip'];
            if (!empty($record['ipv6'])) $ips[]=(string)$record['ipv6'];
        }
    }
    if (!$ips) return false;
    foreach (array_unique($ips) as $ip) {
        if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}
function set_setting(string $k,string $v):void{$s=db()->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');$s->execute([$k,$v]);}
function app_secret():string{
    $file=__DIR__.'/../storage/app-secret.php';
    if(is_file($file)){ $v=require $file; if(is_string($v)&&strlen($v)>=32)return $v; }
    $v=bin2hex(random_bytes(32)); $dir=dirname($file); if(!is_dir($dir))mkdir($dir,0750,true);
    @file_put_contents($file,"<?php return ".var_export($v,true).";\n",LOCK_EX); @chmod($file,0600); return $v;
}
function encrypt_secret(string $plain):string{
    if($plain==='')return '';
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',hash('sha256',app_secret(),true),OPENSSL_RAW_DATA,$iv,$tag);
    return 'ENC1:'.base64_encode($iv.$tag.$cipher);
}
function decrypt_secret(string $stored):string{
    if($stored==='')return '';
    if(strpos($stored,'ENC1:')!==0)return $stored; // backward compatibility with older V3 installs
    $raw=base64_decode(substr($stored,5),true);if($raw===false||strlen($raw)<28)return '';
    $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
    $out=openssl_decrypt($cipher,'aes-256-gcm',hash('sha256',app_secret(),true),OPENSSL_RAW_DATA,$iv,$tag);
    return $out===false?'':$out;
}
function secret_setting(string $k,?string $d=null):?string{ $v=setting($k,null); if($v===null)return $d; $x=decrypt_secret($v); return $x===''?$d:$x; }
function set_secret_setting(string $k,string $v):void{ if($v!=='')set_setting($k,encrypt_secret($v)); }
function secret_is_masked_input(?string $v): bool {
    $v=trim((string)$v);
    return $v!=='' && preg_match('/^[•*·]{4,}$/u',$v)===1;
}
function secret_mask(string $v): string {
    if($v==='') return '';
    return str_repeat('•',8);
}

function plan_limits(string $plan):array{
    if(function_exists('policy_testing_mode') && policy_testing_mode()) return ['tasks'=>PHP_INT_MAX,'projects'=>PHP_INT_MAX,'notes'=>PHP_INT_MAX,'ai_seconds'=>PHP_INT_MAX,'url_monthly_links'=>0,'url_analytics_days'=>0];
    $p=policy_plan($plan);
    $limits=[];
    foreach(['tasks','projects','notes','ai_seconds'] as $key){
        $value=(int)($p[$key]??(['tasks'=>10,'projects'=>10,'notes'=>10,'ai_seconds'=>7200][$key]));
        // For count/time quotas, zero means unlimited, matching the policy matrix convention.
        $limits[$key]=$value<=0?PHP_INT_MAX:$value;
    }
    $limits['url_monthly_links']=(int)($p['url_monthly_links']??50);
    $limits['url_analytics_days']=(int)($p['url_analytics_days']??30);
    return $limits;
}
function plan_limit_reached(string $type,?int $uid=null):bool{
    $u=$uid!==null?null:current_user();
    $uid=$uid??(int)($u['id']??0); if($uid<=0)return false;
    $s=db()->prepare('SELECT plan FROM users WHERE id=?');$s->execute([$uid]);$plan=(string)($s->fetchColumn()?:'free');
    $lim=plan_limits($plan)[$type]??PHP_INT_MAX;if($lim===PHP_INT_MAX)return false;
    $table=['tasks'=>'tasks','projects'=>'projects','notes'=>'notes'][$type]??null;if(!$table)return false;
    $s=db()->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id=?");$s->execute([$uid]);return (int)$s->fetchColumn()>=$lim;
}
function platform_autofill_defaults():array{
    $rules=[]; foreach(['registered_users_display','active_users_display','tasks_created_counter','notes_created_counter','projects_created_counter'] as $k){
        $rules[$k]=['enabled'=>false,'mode'=>'manual','unit'=>'number','amount'=>1,'max'=>0,'interval'=>600,'last_run'=>time()];
    }
    return $rules;
}
function platform_autofill_rules():array{
    $raw=setting('platform_autofill_rules','');$data=json_decode((string)$raw,true);$base=platform_autofill_defaults();
    if(is_array($data))foreach($base as $k=>$v)if(isset($data[$k])&&is_array($data[$k]))$base[$k]=array_merge($v,$data[$k]);
    return $base;
}
function platform_autofill_save(array $rules):void{set_setting('platform_autofill_rules',json_encode($rules,JSON_UNESCAPED_SLASHES));}
function platform_autofill_apply():void{
    static $ran=false;if($ran)return;$ran=true;
    try{
        $rules=platform_autofill_rules();$now=time();$changed=false;
        foreach($rules as $key=>$r){
            if(empty($r['enabled']))continue;
            $interval=max(60,(int)($r['interval']??600));$last=(int)($r['last_run']??$now);
            $steps=(int)floor(($now-$last)/$interval);if($steps<1)continue;
            // Bound catch-up work but preserve unprocessed elapsed intervals for the next request.
            $steps=min(1000,$steps);$value=max(0,(int)(setting($key,'0')??0));$max=max(0,(int)($r['max']??0));
            $unit=($r['unit']??'number')==='percent'?'percent':'number';$mode=($r['mode']??'manual')==='auto'?'auto':'manual';$amount=max(0,(float)($r['amount']??1));
            for($i=0;$i<$steps;$i++){
                if($amount<=0)break;
                if($unit==='percent'){
                    $pct=$amount;
                    if($mode==='auto'){$randomMax=(int)floor($amount);if($randomMax<1)break;$pct=mt_rand(1,$randomMax);}
                    $inc=(int)ceil($value*($pct/100));
                    if($value>0&&$pct>0&&$inc<1)$inc=1;
                }else{
                    $inc=(int)round($amount);
                    if($mode==='auto'){$randomMax=(int)floor($amount);if($randomMax<1)break;$inc=mt_rand(1,$randomMax);}
                }
                $value+=$inc;
                if($max>0&&$value>=$max){$value=$max;break;}
            }
            if($max>0)$value=min($value,$max);
            set_setting($key,(string)$value);$rules[$key]['last_run']=$last+$steps*$interval;$changed=true;
        }
        if((int)(setting('latest_month_linked','1')??1)===1)set_setting('latest_month_users',(string)(setting('registered_users_display','0')??'0'));
        if($changed)platform_autofill_save($rules);
    }catch(Throwable $e){}
}
function ensure_platform_schema():void{
    static $done=false;if($done||!is_installed())return;$done=true;
    try{
        $pdo=db();
        $cols=$pdo->query("SHOW COLUMNS FROM users LIKE 'plan'")->fetch();
        if(!$cols)$pdo->exec("ALTER TABLE users ADD COLUMN plan ENUM('free','premium') NOT NULL DEFAULT 'free' AFTER role");
        try{$pdo->exec("ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER updated_at");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE users ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER last_seen_at");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_daily(user_id BIGINT UNSIGNED NOT NULL,usage_date DATE NOT NULL,input_units INT UNSIGNED NOT NULL DEFAULT 0,output_units INT UNSIGNED NOT NULL DEFAULT 0,active_seconds DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL,PRIMARY KEY(user_id,usage_date),CONSTRAINT fk_ai_usage_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS short_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,slug VARCHAR(64) NOT NULL UNIQUE,destination_url TEXT NOT NULL,title VARCHAR(220) NULL,password_hash VARCHAR(255) NULL,expires_at DATETIME NULL,max_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,last_clicked_at DATETIME NULL,allowed_countries TEXT NULL,blocked_countries TEXT NULL,routing_rules LONGTEXT NULL,deep_link_url TEXT NULL,web_fallback_url TEXT NULL,utm_json TEXT NULL,tracking_pixels LONGTEXT NULL,og_title VARCHAR(220) NULL,og_description TEXT NULL,og_image_url TEXT NULL,status ENUM('active','disabled','expired') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),INDEX(status),INDEX(expires_at),INDEX(clicks),CONSTRAINT fk_short_link_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_clicks(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,link_id BIGINT UNSIGNED NOT NULL,visitor_hash CHAR(64) NOT NULL,ip_hash CHAR(64) NOT NULL,referrer VARCHAR(500) NULL,browser VARCHAR(80) NOT NULL,os VARCHAR(50) NOT NULL,device_category VARCHAR(30) NOT NULL,country_code CHAR(2) NULL,city VARCHAR(120) NULL,is_bot TINYINT(1) NOT NULL DEFAULT 0,user_agent VARCHAR(1000) NULL,clicked_at DATETIME NOT NULL,INDEX(link_id),INDEX(clicked_at),INDEX(link_id,clicked_at),INDEX(link_id,is_bot),INDEX(link_id,visitor_hash),CONSTRAINT fk_link_click_link FOREIGN KEY(link_id) REFERENCES short_links(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_api_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,token_prefix VARCHAR(20) NOT NULL,created_at DATETIME NOT NULL,last_used_at DATETIME NULL,revoked_at DATETIME NULL,INDEX(user_id),CONSTRAINT fk_link_token_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_webhooks(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,link_id BIGINT UNSIGNED NULL,url TEXT NOT NULL,secret VARCHAR(190) NOT NULL,events_json TEXT NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,INDEX(user_id),INDEX(link_id),CONSTRAINT fk_link_webhook_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_link_webhook_link FOREIGN KEY(link_id) REFERENCES short_links(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_webhook_deliveries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,webhook_id BIGINT UNSIGNED NOT NULL,event_name VARCHAR(80) NOT NULL,status_code INT NOT NULL DEFAULT 0,response_body TEXT NULL,created_at DATETIME NOT NULL,INDEX(webhook_id),INDEX(created_at),CONSTRAINT fk_webhook_delivery_hook FOREIGN KEY(webhook_id) REFERENCES link_webhooks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                ensure_link_analytics_schema($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS file_objects(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,public_token CHAR(32) NOT NULL UNIQUE,object_key VARCHAR(600) NOT NULL UNIQUE,original_name VARCHAR(220) NOT NULL,mime_type VARCHAR(150) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,kind VARCHAR(30) NOT NULL DEFAULT 'file',source_type VARCHAR(40) NOT NULL DEFAULT 'storage',source_category VARCHAR(80) NOT NULL DEFAULT 'Shared Files',etag VARCHAR(120) NULL,storage_driver VARCHAR(30) NOT NULL DEFAULT 'local',status ENUM('pending','clean','quarantined','expired','deleted') NOT NULL DEFAULT 'pending',scan_status ENUM('pending','clean','infected','failed','skipped') NOT NULL DEFAULT 'pending',password_hash VARCHAR(255) NULL,expires_at DATETIME NULL,max_downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,burn_after_reading TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),INDEX(status),INDEX(expires_at),INDEX(scan_status),INDEX(downloads),CONSTRAINT fk_file_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN storage_driver VARCHAR(30) NOT NULL DEFAULT 'local'");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN provider_url TEXT NULL AFTER storage_driver");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN relative_path VARCHAR(600) NULL AFTER original_name");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN view_only TINYINT(1) NOT NULL DEFAULT 0 AFTER burn_after_reading");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_share_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,token CHAR(48) NOT NULL UNIQUE,title VARCHAR(220) NOT NULL,password_hash VARCHAR(255) NULL,expires_at DATETIME NULL,max_downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,view_only TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),INDEX(expires_at),CONSTRAINT fk_file_share_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_share_items(share_id BIGINT UNSIGNED NOT NULL,file_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY(share_id,file_id),CONSTRAINT fk_file_share_item_share FOREIGN KEY(share_id) REFERENCES file_share_links(id) ON DELETE CASCADE,CONSTRAINT fk_file_share_item_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_versions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,file_id BIGINT UNSIGNED NOT NULL,version_no INT UNSIGNED NOT NULL,object_key VARCHAR(600) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,etag VARCHAR(120) NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_file_version(file_id,version_no),INDEX(file_id,created_at),CONSTRAINT fk_file_version_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $pdo->exec("CREATE TABLE IF NOT EXISTS upload_sessions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,token CHAR(64) NOT NULL UNIQUE,scope VARCHAR(30) NOT NULL,source_type VARCHAR(40) NOT NULL DEFAULT 'files',source_category VARCHAR(80) NOT NULL DEFAULT 'Shared Files',context_id BIGINT UNSIGNED NOT NULL DEFAULT 0,original_name VARCHAR(220) NOT NULL,relative_path VARCHAR(600) NULL,target_folder VARCHAR(600) NULL,mime_type VARCHAR(150) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL,received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,temp_path VARCHAR(900) NOT NULL,final_path VARCHAR(900) NULL,public_path VARCHAR(900) NULL,status ENUM('uploading','completed','aborted','failed') NOT NULL DEFAULT 'uploading',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id,status),INDEX(user_id,created_at),CONSTRAINT fk_upload_session_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE upload_sessions ADD COLUMN target_folder VARCHAR(600) NULL AFTER relative_path");}catch(Throwable $e){}
$pdo->exec("CREATE TABLE IF NOT EXISTS file_uploads(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,file_id BIGINT UNSIGNED NOT NULL,upload_id VARCHAR(255) NOT NULL,parts_json LONGTEXT NULL,expected_parts INT UNSIGNED NOT NULL DEFAULT 0,completed_parts INT UNSIGNED NOT NULL DEFAULT 0,status ENUM('initiated','uploading','completed','aborted','failed') NOT NULL DEFAULT 'initiated',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_file_upload(file_id),CONSTRAINT fk_file_upload_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_events(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,file_id BIGINT UNSIGNED NOT NULL,event_type ENUM('view','download','download_complete','download_aborted') NOT NULL,visitor_hash CHAR(64) NOT NULL,ip_hash CHAR(64) NOT NULL,referrer VARCHAR(500) NULL,browser VARCHAR(80) NOT NULL,os VARCHAR(50) NOT NULL,device_category VARCHAR(30) NOT NULL,country_code CHAR(2) NULL,city VARCHAR(120) NULL,is_bot TINYINT(1) NOT NULL DEFAULT 0,bytes_transferred BIGINT UNSIGNED NOT NULL DEFAULT 0,completed TINYINT(1) NOT NULL DEFAULT 0,occurred_at DATETIME NOT NULL,INDEX(file_id),INDEX(file_id,occurred_at),INDEX(file_id,event_type),INDEX(file_id,is_bot),CONSTRAINT fk_file_event_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_webhooks(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,url TEXT NOT NULL,secret VARCHAR(190) NOT NULL,events_json TEXT NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,INDEX(user_id),CONSTRAINT fk_file_webhook_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_webhook_deliveries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,webhook_id BIGINT UNSIGNED NOT NULL,event_name VARCHAR(80) NOT NULL,status_code INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,INDEX(webhook_id),INDEX(created_at),CONSTRAINT fk_file_delivery_hook FOREIGN KEY(webhook_id) REFERENCES file_webhooks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_api_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,token_prefix VARCHAR(20) NOT NULL,created_at DATETIME NOT NULL,last_used_at DATETIME NULL,revoked_at DATETIME NULL,INDEX(user_id),CONSTRAINT fk_file_token_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_abuse_blocks(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL UNIQUE,reason VARCHAR(255) NOT NULL,expires_at DATETIME NULL,created_at DATETIME NOT NULL,INDEX(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE file_abuse_blocks ADD COLUMN ip_encrypted TEXT NULL AFTER ip_hash");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS file_abuse_history(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL,ip_encrypted TEXT NULL,action ENUM('block','unblock') NOT NULL,duration_label VARCHAR(40) NULL,reason VARCHAR(255) NULL,admin_user_id BIGINT UNSIGNED NULL,expires_at DATETIME NULL,created_at DATETIME NOT NULL,INDEX(ip_hash),INDEX(created_at),CONSTRAINT fk_abuse_history_admin FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE file_abuse_history ADD COLUMN ip_encrypted TEXT NULL AFTER ip_hash");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_rate_limits(bucket CHAR(64) PRIMARY KEY,window_start DATETIME NOT NULL,hits INT UNSIGNED NOT NULL DEFAULT 0,INDEX(window_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_ai_settings(user_id BIGINT UNSIGNED PRIMARY KEY,bedrock_api_key_encrypted TEXT NULL,aws_access_key_encrypted TEXT NULL,aws_secret_key_encrypted TEXT NULL,aws_session_token_encrypted TEXT NULL,region VARCHAR(80) NULL,model_id VARCHAR(190) NULL,endpoint_url TEXT NULL,api_mode VARCHAR(30) NULL,transport VARCHAR(30) NULL,relay_url TEXT NULL,relay_token_encrypted TEXT NULL,updated_at DATETIME NOT NULL,CONSTRAINT fk_user_ai_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_user_preferences(user_id BIGINT UNSIGNED PRIMARY KEY,model_id VARCHAR(190) NULL,updated_at DATETIME NOT NULL,CONSTRAINT fk_ai_preferences_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_project_settings(project_id BIGINT UNSIGNED PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,custom_instructions TEXT NULL,context_text LONGTEXT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),CONSTRAINT fk_ai_project_settings_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,CONSTRAINT fk_ai_project_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(["ALTER TABLE conversations ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER user_id","ALTER TABLE conversations ADD COLUMN model_id VARCHAR(190) NULL AFTER title","ALTER TABLE conversations ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER model_id","ALTER TABLE conversations ADD INDEX idx_conversation_project(project_id)","ALTER TABLE conversations ADD INDEX idx_conversation_user_updated(user_id,updated_at)","ALTER TABLE conversations ADD CONSTRAINT fk_conversation_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL"] as $sql){try{$pdo->exec($sql);}catch(Throwable $e){}}
        try{$pdo->exec("ALTER TABLE ai_usage_daily ADD COLUMN active_seconds DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0 AFTER output_units");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_content(page_slug VARCHAR(80) PRIMARY KEY,title VARCHAR(180) NOT NULL DEFAULT '',html_content LONGTEXT NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS media_assets(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(180) NOT NULL,source_type ENUM('upload','url') NOT NULL,source_category VARCHAR(80) NOT NULL DEFAULT 'Media Assets',source_ref_id BIGINT UNSIGNED NULL,source_url TEXT NOT NULL,width INT UNSIGNED NULL,height INT UNSIGNED NULL,auto_fit TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE media_assets ADD COLUMN folder_name VARCHAR(180) NOT NULL DEFAULT 'General' AFTER name");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE media_assets ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT 'Media Assets' AFTER source_type");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE media_assets ADD COLUMN source_ref_id BIGINT UNSIGNED NULL AFTER source_category");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE media_assets ADD INDEX idx_media_source_category(user_id,source_category,created_at)");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT 'storage' AFTER kind");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT 'Shared Files' AFTER source_type");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE file_objects ADD INDEX idx_file_source_category(user_id,source_category,created_at)");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE chat_attachments ADD COLUMN storage_path VARCHAR(900) NULL AFTER stored_path");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE chat_attachments ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT 'ai' AFTER kind");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE chat_attachments ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT 'AI Workspace' AFTER source_type");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE chat_attachments ADD INDEX idx_chat_source_category(user_id,source_category,created_at)");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE support_messages ADD COLUMN attachment_category VARCHAR(80) NULL AFTER attachment_paths");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE upload_sessions ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT 'files' AFTER scope");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE upload_sessions ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT 'Shared Files' AFTER source_type");}catch(Throwable $e){}
        // Media tenancy: keep legacy/admin assets nullable, but every user-owned asset carries its authenticated owner.
        try{$pdo->exec("ALTER TABLE media_assets ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id");}catch(Throwable $e){}
        try{$pdo->exec("CREATE INDEX idx_media_user_folder ON media_assets(user_id,folder_name)");}catch(Throwable $e){}
        try{$pdo->exec("CREATE INDEX idx_media_user_created ON media_assets(user_id,created_at)");}catch(Throwable $e){}
        foreach(["ALTER TABLE media_assets ADD COLUMN alt_text VARCHAR(500) NULL AFTER name","ALTER TABLE media_assets ADD COLUMN description TEXT NULL AFTER alt_text","ALTER TABLE media_assets ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_fit","ALTER TABLE media_assets ADD COLUMN updated_at DATETIME NULL AFTER created_at"] as $sql){try{$pdo->exec($sql);}catch(Throwable $e){}}
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_connections(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider VARCHAR(40) NOT NULL UNIQUE,account_email VARCHAR(190) NULL,access_token_encrypted TEXT NULL,refresh_token_encrypted TEXT NULL,expires_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach([
            "ALTER TABLE media_assets ADD COLUMN mime_type VARCHAR(150) NULL AFTER source_url",
            "ALTER TABLE media_assets ADD COLUMN size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER mime_type",
            "ALTER TABLE media_assets ADD COLUMN kind ENUM('image','audio','video','archive','file') NOT NULL DEFAULT 'image' AFTER size_bytes",
            "ALTER TABLE media_assets ADD COLUMN storage_path VARCHAR(255) NULL AFTER source_url"
        ] as $migration){try{$pdo->exec($migration);}catch(Throwable $e){}}
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_attachments(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,message_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(220) NOT NULL,stored_path VARCHAR(255) NOT NULL,storage_path VARCHAR(900) NULL,mime_type VARCHAR(150) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,kind ENUM('image','audio','video','archive','file') NOT NULL DEFAULT 'file',source_type VARCHAR(40) NOT NULL DEFAULT 'ai',source_category VARCHAR(80) NOT NULL DEFAULT 'AI Workspace',created_at DATETIME NOT NULL,INDEX(conversation_id),INDEX(user_id),CONSTRAINT fk_chat_attach_conv FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,CONSTRAINT fk_chat_attach_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_chat_attach_msg FOREIGN KEY(message_id) REFERENCES conversation_messages(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE users MODIFY plan ENUM('free','basic','premium') NOT NULL DEFAULT 'free'");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS shared_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token CHAR(64) NOT NULL UNIQUE,user_id BIGINT UNSIGNED NOT NULL,type ENUM('message','conversation') NOT NULL,conversation_id BIGINT UNSIGNED NULL,message_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,INDEX(conversation_id),INDEX(message_id),CONSTRAINT fk_share_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_share_conv FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,CONSTRAINT fk_share_msg FOREIGN KEY(message_id) REFERENCES conversation_messages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_requests(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,plan ENUM('basic','premium') NOT NULL,amount DECIMAL(10,2) NOT NULL,payment_method ENUM('UPI','PhonePe','Google Pay','Net Banking','Bank Transfer') NOT NULL,waiting_token CHAR(20) NOT NULL UNIQUE,status ENUM('waiting','proof_uploaded','approved','rejected') NOT NULL DEFAULT 'waiting',proof_path VARCHAR(255) NULL,admin_note TEXT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),INDEX(status),CONSTRAINT fk_sub_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$pdo->exec("ALTER TABLE subscription_requests MODIFY payment_method ENUM('UPI','PhonePe','Google Pay','Net Banking','Bank Transfer') NOT NULL");}catch(Throwable $e){}
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_threads(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,subscription_request_id BIGINT UNSIGNED NULL,status ENUM('open','closed') NOT NULL DEFAULT 'open',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id),CONSTRAINT fk_thread_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_thread_sub FOREIGN KEY(subscription_request_id) REFERENCES subscription_requests(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,thread_id BIGINT UNSIGNED NOT NULL,sender_user_id BIGINT UNSIGNED NULL,sender_role ENUM('user','admin') NOT NULL,body LONGTEXT NOT NULL,attachment_path VARCHAR(255) NULL,attachment_category VARCHAR(80) NULL,created_at DATETIME NOT NULL,INDEX(thread_id),CONSTRAINT fk_support_thread FOREIGN KEY(thread_id) REFERENCES support_threads(id) ON DELETE CASCADE,CONSTRAINT fk_support_sender FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS policy_user_overrides(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,policy_key VARCHAR(120) NOT NULL,policy_value LONGTEXT NOT NULL,reason VARCHAR(255) NULL,granted_by BIGINT UNSIGNED NULL,expires_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_user_policy(user_id,policy_key),INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS policy_audit_logs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,admin_id BIGINT UNSIGNED NULL,action VARCHAR(120) NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,ip_address VARCHAR(45) NULL,created_at DATETIME NOT NULL,INDEX(admin_id),INDEX(action),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_roles(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,permissions_json LONGTEXT NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_admin_roles(user_id BIGINT UNSIGNED NOT NULL,role_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(user_id,role_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_impersonation_logs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,admin_id BIGINT UNSIGNED NOT NULL,target_user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(30) NOT NULL,ip_address VARCHAR(45) NULL,created_at DATETIME NOT NULL,INDEX(admin_id),INDEX(target_user_id),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS global_block_rules(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,rule_type VARCHAR(30) NOT NULL,value VARCHAR(190) NOT NULL,action VARCHAR(30) NOT NULL DEFAULT 'block',limit_mbps DECIMAL(10,2) NULL,reason VARCHAR(255) NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_global_block(rule_type,value),INDEX(active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS webhook_delivery_log(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,scope VARCHAR(30) NOT NULL,event_name VARCHAR(100) NOT NULL,target_url TEXT NOT NULL,status_code INT NOT NULL DEFAULT 0,response_excerpt TEXT NULL,created_at DATETIME NOT NULL,INDEX(scope),INDEX(event_name),INDEX(status_code),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_request_logs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,token_id BIGINT UNSIGNED NULL,method VARCHAR(10) NOT NULL,path VARCHAR(500) NOT NULL,status_code INT NOT NULL DEFAULT 0,latency_ms DECIMAL(12,3) NOT NULL DEFAULT 0,ip_hash CHAR(64) NULL,user_agent VARCHAR(500) NULL,created_at DATETIME NOT NULL,INDEX(user_id,created_at),INDEX(token_id,created_at),INDEX(status_code,created_at),INDEX(path(191)),INDEX(created_at),CONSTRAINT fk_api_log_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT fk_api_log_token FOREIGN KEY(token_id) REFERENCES link_api_tokens(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach([
            "ALTER TABLE link_webhook_deliveries ADD COLUMN request_body LONGTEXT NULL AFTER response_body",
            "ALTER TABLE link_webhook_deliveries ADD COLUMN request_headers TEXT NULL AFTER request_body",
            "ALTER TABLE link_webhook_deliveries ADD COLUMN response_headers TEXT NULL AFTER request_headers",
            "ALTER TABLE link_webhook_deliveries ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER response_headers"
        ] as $migration){try{$pdo->exec($migration);}catch(Throwable $e){}}
        try{$pdo->exec("ALTER TABLE support_messages ADD COLUMN attachment_paths LONGTEXT NULL");}catch(Throwable $e){}
        $roles=[['super-admin','Super Admin',['*'=>true]],['support-specialist','Support Specialist',['users.read'=>true,'users.impersonate'=>true,'links.read'=>true,'files.read'=>true]],['billing-manager','Billing Manager',['users.read'=>true,'billing.write'=>true,'plans.write'=>true]],['compliance-auditor','Compliance Auditor',['audit.read'=>true,'links.read'=>true,'files.read'=>true]]];
        foreach($roles as $r){$st=$pdo->prepare('INSERT INTO admin_roles(slug,name,permissions_json,created_at,updated_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),permissions_json=VALUES(permissions_json),updated_at=NOW()');$st->execute([$r[0],$r[1],json_encode($r[2]),date('Y-m-d H:i:s')]);}
        $defaults=['registered_users_display'=>'2','active_users_display'=>'1','tasks_created_counter'=>'1070063','notes_created_counter'=>'1560021','projects_created_counter'=>'503098','theme_mode'=>'light','theme_brand'=>'#2563eb','theme_brand2'=>'#7c3aed','theme_bg'=>'#f6f8fc','theme_card'=>'#ffffff','theme_ink'=>'#122033','theme_muted'=>'#68768a','theme_line'=>'#e5eaf1','footer_text'=>'Calm work management for modern teams.','footer_copyright'=>'© '.date('Y').' PocketCat · Based in India','social_x_url'=>'https://x.com/RoyHindinews','theme_high'=>'#b91c1c','theme_medium'=>'#92400e','theme_low'=>'#166534','logo_url'=>'','show_site_name_header'=>'1','redirect_brand_transition'=>'0','growth_base'=>'10000','growth_rates'=>'5,10,15,25,10','label_tasks'=>'Tasks','label_notes'=>'Notes','label_projects'=>'Projects','label_dashboard'=>'Dashboard','label_completed'=>'Completed','label_todo'=>'Todo','label_in_progress'=>'In Progress','label_ai_assistant'=>'AI Assistant','label_search'=>'Search','latest_month_linked'=>'1','latest_month_users'=>'2','show_live_workspace_homepage'=>'1','platform_autofill_rules'=>json_encode(platform_autofill_defaults(),JSON_UNESCAPED_SLASHES)];
        foreach($defaults as $k=>$v)if(setting($k,null)===null)set_setting($k,$v);
        if(setting('file_storage_driver',null)===null)set_setting('file_storage_driver','local');
        if(setting('policy_engine',null)===null)set_setting('policy_engine',json_encode(policy_defaults(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }catch(Throwable $e){}
}
function increment_counter(string $key,int $by=1):void{try{$pdo=db();$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=CAST(setting_value AS UNSIGNED)+VALUES(setting_value),updated_at=NOW()')->execute([$key,(string)$by]);}catch(Throwable $e){}}
function platform_stats():array{
    platform_autofill_apply();
    $keys=['registered_users'=>'registered_users_display','active_users'=>'active_users_display','tasks'=>'tasks_created_counter','notes'=>'notes_created_counter','projects'=>'projects_created_counter'];
    $queries=['registered_users'=>"SELECT COUNT(*) FROM users",'active_users'=>"SELECT COUNT(*) FROM users WHERE status='active' AND last_seen_at >= (NOW() - INTERVAL 15 MINUTE)",'tasks'=>'SELECT COUNT(*) FROM tasks','notes'=>'SELECT COUNT(*) FROM notes','projects'=>'SELECT COUNT(*) FROM projects'];
    $out=[];
    foreach($keys as $name=>$key){
        $stored=setting($key,null);
        if($stored!==null&&$stored!==''){$out[$name]=max(0,(int)$stored);continue;}
        try{$out[$name]=max(0,(int)db()->query($queries[$name])->fetchColumn());}catch(Throwable $e){$out[$name]=0;}
    }
    $linked=setting('latest_month_linked','1')==='1';
    $latest=$linked?$out['registered_users']:max(0,(int)(setting('latest_month_users',(string)$out['registered_users'])??$out['registered_users']));
    if($linked && setting('latest_month_users',(string)$out['registered_users'])!==(string)$out['registered_users'])set_setting('latest_month_users',(string)$out['registered_users']);
    $out['latest_month_users']=$latest;
    $out['show_homepage_section']=setting('show_live_workspace_homepage','1')!=='0';
    return $out;
}
function save_runtime_config(array $db,string $appUrl='https://pocketcat.online'):void{
    $dir=__DIR__.'/../storage';
    if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Could not create runtime storage directory.');
    $file=$dir.'/app-config.php';
    if(file_put_contents($file,"<?php\nreturn ".var_export(['db'=>$db,'app_url'=>rtrim($appUrl,'/')],true).";\n",LOCK_EX)===false)throw new RuntimeException('Could not write runtime configuration.');
    if(!chmod($file,0600))throw new RuntimeException('Could not secure runtime configuration.');
    app_secret();
}
function set_app_url(string $appUrl):void{
    $appUrl=rtrim(trim($appUrl),'/');
    set_setting('app_url',$appUrl);
    $config=app_config();
    $db=$config['db']??null;
    if(!is_array($db))throw new RuntimeException('Database configuration is unavailable.');
    save_runtime_config($db,$appUrl);
}
function site_name_header_visible():bool{return setting('show_site_name_header','1')==='1' && trim((string)setting('app_name','PocketCat'))!=='';}
function site_brand_markup():string{
    $name=trim((string)setting('app_name','PocketCat'));
    $show=site_name_header_visible();
    $logo=trim((string)setting('logo_url',''));
    if($logo!=='') {
        $mark='<img class="site-logo" src="'.e($logo).'" alt="" role="presentation" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false;if(this.nextElementSibling.nextElementSibling)this.nextElementSibling.nextElementSibling.hidden=false"><span class="brand-mark site-logo-fallback" aria-hidden="true" hidden></span>';
        if($show) $mark.='<span class="brand-title" hidden>'.e($name).'</span>';
    } else {
        $mark='<span class="brand-mark" aria-hidden="true"></span>';
        if($show) $mark.='<span class="brand-title">'.e($name).'</span>';
    }
    return $mark;
}
function ui_label(string $key,string $default):string{return (string)(setting('label_'.$key,$default)??$default);}
function site_theme_vars():string{
    $mode=setting('theme_mode','light') ?: 'light';
    $brand=setting('theme_brand','#2563eb') ?: '#2563eb';
    $brand2=setting('theme_brand2','#7c3aed') ?: '#7c3aed';
    $bg=setting('theme_bg','#f6f8fc') ?: '#f6f8fc';
    $card=setting('theme_card','#ffffff') ?: '#ffffff';
    $ink=setting('theme_ink','#122033') ?: '#122033';
    $muted=setting('theme_muted','#68768a') ?: '#68768a';
    $line=setting('theme_line','#e5eaf1') ?: '#e5eaf1';
    $safe=function($x){return preg_match('/^#[0-9a-fA-F]{6}$/',(string)$x)?$x:'#2563eb';};
    $taskHigh=setting('theme_high','#b91c1c') ?: '#b91c1c';$taskMed=setting('theme_medium','#92400e') ?: '#92400e';$taskLow=setting('theme_low','#166534') ?: '#166534';
    $css=':root{--high:'.$safe($taskHigh).';--medium:'.$safe($taskMed).';--low:'.$safe($taskLow).';--brand:'.$safe($brand).';--brand2:'.$safe($brand2).';--bg:'.$safe($bg).';--card:'.$safe($card).';--ink:'.$safe($ink).';--muted:'.$safe($muted).';--line:'.$safe($line).'}';
    if(true){$css.='html[data-theme="dark"]{--bg:#0b1220;--card:#111a2b;--ink:#edf3ff;--muted:#aab7ca;--line:#26344a}html[data-theme="dark"] body{background:var(--bg);color:var(--ink)}html[data-theme="dark"] .card,html[data-theme="dark"] .public-nav,html[data-theme="dark"] .topbar,html[data-theme="dark"] .footer{background:var(--card);color:var(--ink)}html[data-theme="dark"] input,html[data-theme="dark"] textarea,html[data-theme="dark"] select{background:#0f1828;color:var(--ink);border-color:var(--line)}';}
    return '<style>'.$css.'</style><script>document.documentElement.dataset.theme='.json_encode($mode).';</script>';
}
function page_start(string $title,bool $app=true):void{
    ensure_platform_schema();
    $siteName=trim((string)setting('app_name','PocketCat'));$documentTitle=$siteName!==''?$title.' · '.$siteName:$title;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($documentTitle).'</title><meta name="description" content="'.e(setting('site_meta_description','PocketCat productivity workspace')).'"><link rel="stylesheet" href="/assets/css/app.css?v=19.78-extensionless-post-upload-routes1"><link rel="stylesheet" href="/assets/css/secret-controls.css?v=11.42"><script defer src="/assets/js/secret-controls.js?v=11.31"></script>'.site_theme_vars().'<link rel="stylesheet" href="/assets/css/responsive-system.css?v=19.79-brand-fallback"><script>window.POCKETCAT_CSRF='.json_encode(csrf_token()).';</script><script defer src="/assets/js/app.js?v=11.42"></script><script defer src="/assets/js/ai.js?v=11.42"></script></head><body>';
    if($app){require __DIR__.'/navbar.php';require __DIR__.'/sidebar.php';echo '<main class="main">'; if(function_exists('policy_testing_mode') && policy_testing_mode()) echo '<div class="global-testing-banner" role="status"><strong>Testing Mode</strong><span>All Features Unlocked · Paid plan limits and subscription checks are bypassed for testing.</span></div>'; if(!empty($_SESSION['impersonated_user_id'])) echo '<div class="flash install-note">Support impersonation active for this user. <a class="btn small danger" href="/admin/impersonate-stop.php">Exit impersonation</a></div>'; }
    foreach(flashes() as $f) echo '<div class="flash '.e($f[0]).'">'.e($f[1]).'</div>';
    focus_script();
}
function page_end():void{echo "</main></body></html>";}
function focus_script():void{
    echo '<script>(function(){function esc(v){try{return CSS.escape(v)}catch(e){return String(v).replace(/[^a-zA-Z0-9_-]/g,"\\$&")}}function wrap(root,term){if(!term)return[];var nodes=[];var walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT,{acceptNode:function(n){var p=n.parentElement;if(!p||/^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA|INPUT|SELECT|OPTION|MARK)$/i.test(p.tagName))return NodeFilter.FILTER_REJECT;var v=n.nodeValue||"",a=v.toLocaleLowerCase(),b=term.toLocaleLowerCase();return a.indexOf(b)!==-1?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT}});while(walker.nextNode())nodes.push(walker.currentNode);var marks=[];nodes.forEach(function(n){var text=n.nodeValue||"",lower=text.toLocaleLowerCase(),needle=term.toLocaleLowerCase(),frag=document.createDocumentFragment(),pos=0,i;while((i=lower.indexOf(needle,pos))!==-1){if(i>pos)frag.appendChild(document.createTextNode(text.slice(pos,i)));var m=document.createElement("mark");m.className="search-highlight";m.textContent=text.slice(i,i+term.length);frag.appendChild(m);marks.push(m);pos=i+term.length}if(pos<text.length)frag.appendChild(document.createTextNode(text.slice(pos)));if(n.parentNode)n.parentNode.replaceChild(frag,n)});return marks}function unwrap(marks){marks.forEach(function(m){if(!m.parentNode)return;var f=document.createDocumentFragment();while(m.firstChild)f.appendChild(m.firstChild);m.parentNode.replaceChild(f,m)})}function run(){var params=new URLSearchParams(location.search),id=params.get("focus")||location.hash.replace(/^#/ ,""),term=(params.get("highlight")||"").trim();if(!id)return;var el=document.getElementById(id);if(!el){el=document.querySelector("[data-focus-key=\""+esc(id)+"\"]")}if(!el)return;requestAnimationFrame(function(){el.scrollIntoView({behavior:"smooth",block:"center",inline:"nearest"});el.classList.remove("search-target-border-blink");void el.offsetWidth;el.classList.add("search-target-border-blink");setTimeout(function(){el.classList.remove("search-target-border-blink")},5000);var marks=wrap(el,term);setTimeout(function(){unwrap(marks)},10000)})}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run()})();</script>';
}

function public_page_slug():string{
    $p=trim(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/','/');
    if($p==='')return 'home';
    $first=explode('/',$p)[0];
    return preg_replace('/[^a-z0-9_-]/i','',$first) ?: 'home';
}
function site_override_html(string $slug):?string{
    try{$s=db()->prepare('SELECT html_content FROM site_content WHERE page_slug=? LIMIT 1');$s->execute([$slug]);$v=$s->fetchColumn();return $v===false||trim((string)$v)===''?null:(string)$v;}catch(Throwable $e){return null;}
}
function public_header(string $title):void{
    page_start($title,false); $logged=current_user();
    $auth=$logged?'<a class="btn small" href="/dashboard/">Dashboard</a><a class="public-signout" href="/logout/">Sign out</a>':'<a class="btn secondary small" href="/login/">Login</a><a class="btn small" href="/signup/">Create account</a>';
    $navHome=setting('nav_home_label','')?'<a href="/">'.e(setting('nav_home_label','Home')).'</a>':'';
    $nav=''; try{if(function_exists('menu_render'))$nav=menu_render('header','pc-menu');}catch(Throwable $e){} if($nav==='')$nav='<ul class="pc-menu-list"><li>'.$navHome.'</li><li><a href="/product/">'.e(setting('nav_product_label','Product')).'</a></li><li><a href="/features/">'.e(setting('nav_features_label','Features')).'</a></li><li><a href="/how-it-works/">'.e(setting('nav_how_label','How it works')).'</a></li><li><a href="/pricing/">'.e(setting('nav_pricing_label','Pricing')).'</a></li><li><a href="/about/">'.e(setting('nav_about_label','About')).'</a></li><li><a href="/contact/">'.e(setting('nav_contact_label','Contact')).'</a></li></ul>'; 
    $brand=site_brand_markup();
    $publicMenu=$nav.'<div class="public-drawer-actions"><a href="/code/">Code Editor</a><a href="/docs/">Help & Documentation</a><a href="/">View site</a></div>'.$auth;
    echo '<header class="public-nav"><button class="public-hamburger" type="button" data-public-menu-toggle aria-expanded="false" aria-controls="public-drawer" aria-label="Open site menu"><span></span><span></span><span></span></button><a class="brand" href="/" aria-label="'.e(trim((string)setting('app_name','')) ?: 'Home').'">'.$brand.'</a><nav>'.$nav.'<button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">🌙</button>'.$auth.'</nav></header><div class="public-backdrop" data-public-backdrop aria-hidden="true"></div><aside class="public-drawer" id="public-drawer" data-public-drawer aria-label="Site navigation"><div class="public-drawer-head">'.(site_name_header_visible()?'<strong>'.e(setting('app_name','PocketCat')).'</strong>':'').'<button type="button" class="public-drawer-close" data-public-menu-toggle aria-label="Close site menu">×</button></div>'.$publicMenu.'</aside>';
    $GLOBALS['_public_buffer']=true; $GLOBALS['_public_slug']=public_page_slug(); ob_start();
}
function public_footer():void{
    $body=$GLOBALS['_public_buffer']?ob_get_clean():''; $GLOBALS['_public_buffer']=false;
    $override=site_override_html($GLOBALS['_public_slug']??public_page_slug()); echo $override!==null?$override:$body;
    $publicAnalyticsKey=trim((string)setting('analytics_public_site_key','')); if($publicAnalyticsKey!=='') echo '<script async src="/assets/js/site-analytics.js?v=11.42" data-site="'.e($publicAnalyticsKey).'"></script>';
    $footerText=setting('footer_text','Calm work management for modern teams.');
    $xUrl=trim((string)setting('social_x_url','https://x.com/RoyHindinews'));
    $liUrl=trim((string)setting('social_linkedin_url','')); $ghUrl=trim((string)setting('social_github_url',''));
    $social='';
    if($xUrl!=='') $social.='<a class="footer-social-link footer-x-link" href="'.e($xUrl).'" target="_blank" rel="noopener noreferrer nofollow" aria-label="X / Twitter - PocketCat" title="Follow PocketCat on X"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18.9 2H22l-6.78 7.75L23.2 22h-6.25l-4.9-7.61L5.4 22H2.27l7.25-8.29L1.8 2h6.4l4.43 6.96L18.9 2Zm-1.1 17.9h1.73L7.27 3.98H5.41L17.8 19.9Z"/></svg><span>X / Twitter</span></a>';
    if($liUrl!=='')$social.='<a class="footer-social-link" href="'.e($liUrl).'" target="_blank" rel="noopener noreferrer nofollow">LinkedIn</a>';
    if($ghUrl!=='')$social.='<a class="footer-social-link" href="'.e($ghUrl).'" target="_blank" rel="noopener noreferrer nofollow">GitHub</a>';
    $footerNav=''; try{if(function_exists('menu_render'))$footerNav=menu_render('footer','pc-menu');}catch(Throwable $e){}
    if($footerNav==='')$footerNav='<ul class="pc-menu-list"><li><a href="/product/">Product</a></li><li><a href="/features/">Features</a></li><li><a href="/how-it-works/">How it works</a></li><li><a href="/pricing/">Pricing</a></li><li><a href="/about/">About</a></li><li><a href="/faq/">FAQ</a></li><li><a href="/docs/">Help &amp; Documentation</a></li><li><a href="/support/">Support</a></li><li><a href="/contact/">Contact</a></li><li><a href="/security/">Security</a></li><li><a href="/privacy/">Privacy</a></li><li><a href="/terms/">Terms</a></li><li><a href="/acceptable-use/">Acceptable Use</a></li><li><a href="/cookie-policy/">Cookie Policy</a></li></ul>';
    // Preserve admin-managed menu items, but append useful public/help/legal links that are missing.
    $footerExtras=[['how-it-works','How it works','/how-it-works/'],['about','About','/about/'],['docs','Help &amp; Documentation','/docs/'],['support','Support','/support/'],['acceptable-use','Acceptable Use','/acceptable-use/'],['cookie-policy','Cookie Policy','/cookie-policy/']];
    $missing=''; foreach($footerExtras as [$slug,$label,$url]){if(strpos($footerNav,'href="'.$url.'"')===false)$missing.='<li class="pc-menu-item"><a href="'.$url.'">'.$label.'</a></li>';}
    if($missing!==''){$last=strrpos($footerNav,'</ul>');if($last!==false)$footerNav=substr($footerNav,0,$last).$missing.substr($footerNav,$last);}
    echo '<footer class="footer"><div class="footer-main"><div class="footer-brand"><strong>'.e(setting('app_name','PocketCat')).'</strong><p>'.e($footerText).'</p></div><div class="footer-links">'.$footerNav.'<div class="footer-social" aria-label="Social media">'.$social.'</div></div></div><small class="footer-copyright">'.e(setting('footer_copyright','© '.date('Y').' PocketCat · Based in India')).'</small></footer></body></html>';
}

function ensure_link_analytics_schema(PDO $pdo):void{
    try{
        $pdo->exec("CREATE TABLE IF NOT EXISTS link_click_queue(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,link_id BIGINT UNSIGNED NOT NULL,payload_json LONGTEXT NOT NULL,status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,available_at DATETIME NOT NULL,locked_at DATETIME NULL,processed_at DATETIME NULL,last_error VARCHAR(500) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(status,available_at),INDEX(link_id,created_at),CONSTRAINT fk_click_queue_link FOREIGN KEY(link_id) REFERENCES short_links(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $cols=[];foreach($pdo->query("SHOW COLUMNS FROM link_clicks")->fetchAll() as $c)$cols[$c['Field']]=true;
        $add=[
          'ip_address'=>"ALTER TABLE link_clicks ADD COLUMN ip_address VARCHAR(45) NULL AFTER ip_hash",
          'browser_version'=>"ALTER TABLE link_clicks ADD COLUMN browser_version VARCHAR(40) NULL AFTER browser",
          'browser_engine'=>"ALTER TABLE link_clicks ADD COLUMN browser_engine VARCHAR(40) NULL AFTER browser_version",
          'os_version'=>"ALTER TABLE link_clicks ADD COLUMN os_version VARCHAR(40) NULL AFTER os",
          'device_brand'=>"ALTER TABLE link_clicks ADD COLUMN device_brand VARCHAR(80) NULL AFTER device_category",
          'device_model'=>"ALTER TABLE link_clicks ADD COLUMN device_model VARCHAR(120) NULL AFTER device_brand",
          'country_name'=>"ALTER TABLE link_clicks ADD COLUMN country_name VARCHAR(120) NULL AFTER country_code",
          'region'=>"ALTER TABLE link_clicks ADD COLUMN region VARCHAR(120) NULL AFTER country_name",
          'postal_code'=>"ALTER TABLE link_clicks ADD COLUMN postal_code VARCHAR(30) NULL AFTER city",
          'latitude'=>"ALTER TABLE link_clicks ADD COLUMN latitude DECIMAL(10,7) NULL AFTER postal_code",
          'longitude'=>"ALTER TABLE link_clicks ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude",
          'timezone_name'=>"ALTER TABLE link_clicks ADD COLUMN timezone_name VARCHAR(80) NULL AFTER longitude",
          'isp'=>"ALTER TABLE link_clicks ADD COLUMN isp VARCHAR(190) NULL AFTER timezone_name",
          'asn'=>"ALTER TABLE link_clicks ADD COLUMN asn VARCHAR(40) NULL AFTER isp",
          'accept_language'=>"ALTER TABLE link_clicks ADD COLUMN accept_language VARCHAR(120) NULL AFTER asn",
          'utm_source'=>"ALTER TABLE link_clicks ADD COLUMN utm_source VARCHAR(190) NULL AFTER accept_language",
          'utm_medium'=>"ALTER TABLE link_clicks ADD COLUMN utm_medium VARCHAR(190) NULL AFTER utm_source",
          'utm_campaign'=>"ALTER TABLE link_clicks ADD COLUMN utm_campaign VARCHAR(190) NULL AFTER utm_medium",
          'utm_term'=>"ALTER TABLE link_clicks ADD COLUMN utm_term VARCHAR(190) NULL AFTER utm_campaign",
          'utm_content'=>"ALTER TABLE link_clicks ADD COLUMN utm_content VARCHAR(190) NULL AFTER utm_term",
          'bot_type'=>"ALTER TABLE link_clicks ADD COLUMN bot_type VARCHAR(80) NULL AFTER is_bot",
          'clicked_at_utc'=>"ALTER TABLE link_clicks ADD COLUMN clicked_at_utc DATETIME NULL AFTER clicked_at"
        ];
        foreach($add as $name=>$sql)if(!isset($cols[$name])){try{$pdo->exec($sql);}catch(Throwable $e){}}
        try{$pdo->exec("ALTER TABLE link_clicks ADD INDEX idx_link_click_geo(link_id,latitude,longitude)");}catch(Throwable $e){}
        try{$pdo->exec("ALTER TABLE link_clicks ADD INDEX idx_link_click_utm(link_id,utm_source,utm_campaign)");}catch(Throwable $e){}
        if(class_exists('ReflectionFunction')){}
    }catch(Throwable $e){}
}
