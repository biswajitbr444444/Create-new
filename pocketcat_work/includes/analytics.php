<?php
declare(strict_types=1);

function analytics_is_premium(?array $u=null): bool {
    $u=$u?:current_user();
    return (function_exists('policy_testing_mode') && policy_testing_mode()) || ((bool)$u && (($u['role']??'')==='admin' || (($u['plan']??'free')==='premium')));
}
function analytics_require_premium(?array $u=null): void {
    $u=$u?:current_user();
    if(!$u){require_login();$u=current_user();}
    if(!analytics_is_premium($u)){
        http_response_code(402);
        exit('Advanced Website Analytics is a Premium feature.');
    }
}
function analytics_key(): string { return 'pc_'.bin2hex(random_bytes(20)); }
function analytics_hash(string $value): string { return hash_hmac('sha256',$value,app_secret()); }
function analytics_parse_ua(string $ua): array {
    $browser='Other';$version='';$os='Other';$device='Desktop';
    if(preg_match('/Edg\/([\d.]+)/i',$ua,$m)){ $browser='Edge';$version=$m[1]; }
    elseif(preg_match('/Chrome\/([\d.]+)/i',$ua,$m)){ $browser='Chrome';$version=$m[1]; }
    elseif(preg_match('/Firefox\/([\d.]+)/i',$ua,$m)){ $browser='Firefox';$version=$m[1]; }
    elseif(preg_match('/Version\/([\d.]+).*Safari/i',$ua,$m)){ $browser='Safari';$version=$m[1]; }
    elseif(preg_match('/OPR\/([\d.]+)/i',$ua,$m)){ $browser='Opera';$version=$m[1]; }
    if(preg_match('/Android/i',$ua))$os='Android';elseif(preg_match('/iPhone|iPad|iPod/i',$ua))$os='iOS';elseif(preg_match('/Windows/i',$ua))$os='Windows';elseif(preg_match('/Mac OS X/i',$ua))$os='macOS';elseif(preg_match('/Linux/i',$ua))$os='Linux';
    if(preg_match('/Mobile|Android.*Mobile|iPhone|iPod/i',$ua))$device='Mobile';elseif(preg_match('/iPad|Android(?!.*Mobile)/i',$ua))$device='Tablet';
    return compact('browser','version','os','device');
}
function analytics_column_exists(PDO $pdo,string $table,string $column):bool{ $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$table,$column]);return (int)$q->fetchColumn()>0; }
function analytics_table_exists(PDO $pdo,string $table):bool{ $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);return (int)$q->fetchColumn()>0; }
function analytics_add_column(PDO $pdo,string $table,string $column,string $definition):void{ if(!analytics_column_exists($pdo,$table,$column))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); }
function ensure_advanced_analytics_schema(PDO $pdo): void {
    try {
        if(analytics_table_exists($pdo,'analytics_events') && !analytics_column_exists($pdo,'analytics_events','site_id')){
            if(analytics_table_exists($pdo,'analytics_events_legacy'))throw new RuntimeException('Legacy analytics events could not be preserved because analytics_events_legacy already exists.');
            $pdo->exec('RENAME TABLE analytics_events TO analytics_events_legacy');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_sites(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,owner_user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(180) NOT NULL,domain VARCHAR(255) NULL,tracking_key VARCHAR(80) NOT NULL UNIQUE,enabled TINYINT(1) NOT NULL DEFAULT 1,consent_required TINYINT(1) NOT NULL DEFAULT 1,anonymize_ip TINYINT(1) NOT NULL DEFAULT 1,retention_days INT UNSIGNED NOT NULL DEFAULT 365,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(owner_user_id),INDEX(enabled),CONSTRAINT fk_analytics_site_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_sessions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id BIGINT UNSIGNED NOT NULL,session_hash CHAR(64) NOT NULL,visitor_hash CHAR(64) NOT NULL,started_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,landing_path VARCHAR(500) NULL,exit_path VARCHAR(500) NULL,pageviews INT UNSIGNED NOT NULL DEFAULT 0,event_count INT UNSIGNED NOT NULL DEFAULT 0,engagement_seconds INT UNSIGNED NOT NULL DEFAULT 0,converted TINYINT(1) NOT NULL DEFAULT 0,referrer VARCHAR(500) NULL,utm_source VARCHAR(190) NULL,utm_medium VARCHAR(190) NULL,utm_campaign VARCHAR(190) NULL,country_code CHAR(2) NULL,device_category VARCHAR(30) NULL,browser VARCHAR(80) NULL,os VARCHAR(50) NULL,ip_hash CHAR(64) NULL,INDEX(site_id,last_seen_at),INDEX(site_id,started_at),INDEX(site_id,visitor_hash),INDEX(site_id,session_hash),CONSTRAINT fk_analytics_session_site FOREIGN KEY(site_id) REFERENCES analytics_sites(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_events(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id BIGINT UNSIGNED NOT NULL,session_hash CHAR(64) NOT NULL,visitor_hash CHAR(64) NOT NULL,event_name VARCHAR(80) NOT NULL,page_path VARCHAR(500) NULL,page_title VARCHAR(220) NULL,referrer VARCHAR(500) NULL,utm_source VARCHAR(190) NULL,utm_medium VARCHAR(190) NULL,utm_campaign VARCHAR(190) NULL,utm_term VARCHAR(190) NULL,utm_content VARCHAR(190) NULL,country_code CHAR(2) NULL,region VARCHAR(120) NULL,city VARCHAR(120) NULL,device_category VARCHAR(30) NULL,browser VARCHAR(80) NULL,browser_version VARCHAR(40) NULL,os VARCHAR(50) NULL,screen_width SMALLINT UNSIGNED NULL,screen_height SMALLINT UNSIGNED NULL,language VARCHAR(40) NULL,ip_hash CHAR(64) NULL,event_value DECIMAL(18,4) NULL,metadata_json TEXT NULL,created_at DATETIME NOT NULL,INDEX(site_id,created_at),INDEX(site_id,event_name,created_at),INDEX(site_id,page_path(191),created_at),INDEX(site_id,session_hash),INDEX(site_id,visitor_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_goals(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,event_name VARCHAR(80) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(site_id,active),CONSTRAINT fk_analytics_goal_site FOREIGN KEY(site_id) REFERENCES analytics_sites(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        analytics_add_column($pdo,'analytics_events','country_name','VARCHAR(120) NULL AFTER country_code');
        analytics_add_column($pdo,'analytics_events','latitude','DECIMAL(10,7) NULL AFTER city');
        analytics_add_column($pdo,'analytics_events','longitude','DECIMAL(10,7) NULL AFTER latitude');
        analytics_add_column($pdo,'analytics_events','timezone_name','VARCHAR(80) NULL AFTER longitude');
        analytics_add_column($pdo,'analytics_sessions','country_name','VARCHAR(120) NULL AFTER country_code');
        analytics_add_column($pdo,'analytics_sessions','latitude','DECIMAL(10,7) NULL AFTER country_name');
        analytics_add_column($pdo,'analytics_sessions','longitude','DECIMAL(10,7) NULL AFTER latitude');
        analytics_add_column($pdo,'analytics_sessions','timezone_name','VARCHAR(80) NULL AFTER longitude');
    } catch(Throwable $e){ error_log('[PocketCat advanced analytics schema] '.$e->getMessage()); }
}
function analytics_get_site(int $id): ?array { try{$s=db()->prepare('SELECT * FROM analytics_sites WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}catch(Throwable $e){return null;} }
function analytics_get_site_by_key(string $key): ?array { try{$s=db()->prepare('SELECT * FROM analytics_sites WHERE tracking_key=? AND enabled=1 LIMIT 1');$s->execute([$key]);return $s->fetch()?:null;}catch(Throwable $e){return null;} }
function analytics_owned_site(int $uid): ?array { try{$s=db()->prepare('SELECT * FROM analytics_sites WHERE owner_user_id=? ORDER BY id ASC LIMIT 1');$s->execute([$uid]);return $s->fetch()?:null;}catch(Throwable $e){return null;} }
function analytics_create_site(int $uid,string $name,string $domain=''): array { analytics_require_premium(); $name=trim($name)?:'My Website';$domain=trim($domain);$key=analytics_key();db()->prepare('INSERT INTO analytics_sites(owner_user_id,name,domain,tracking_key,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())')->execute([$uid,$name,$domain,$key]);return analytics_get_site((int)db()->lastInsertId())??[]; }
function analytics_tracking_snippet(array $site): string { $url=rtrim((string)(app_config()['app_url']??''),'/'); if($url==='')$url='https://pocketcat.online'; return '<script async src="'.$url.'/assets/js/site-analytics.js" data-site="'.e($site['tracking_key']).'" data-consent="'.(!empty($site['consent_required'])?'1':'0').'"></script>'; }
function analytics_geo(string $ip): array { $g=geo_lookup($ip); if(!$g)return ['country'=>'','country_name'=>'','region'=>'','city'=>'','latitude'=>null,'longitude'=>null,'timezone'=>'']; return ['country'=>(string)($g['country_code']??''),'country_name'=>(string)($g['country_name']??''),'region'=>(string)($g['region']??''),'city'=>(string)($g['city']??''),'latitude'=>$g['latitude']??null,'longitude'=>$g['longitude']??null,'timezone'=>(string)($g['timezone']??'')]; }
function analytics_collect(array $payload, ?array $site=null): array {
    $key=trim((string)($payload['site_key']??''));$site=$site?:analytics_get_site_by_key($key);if(!$site)throw new RuntimeException('Analytics site is disabled or invalid.');
    $pdo=db();$now=date('Y-m-d H:i:s');$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$uaInfo=analytics_parse_ua($ua);$ip=client_ip();$visitor=analytics_hash(substr((string)($payload['visitor_id']??''),0,160).'|'.$site['id']);$session=analytics_hash(substr((string)($payload['session_id']??''),0,160).'|'.$site['id']);if($visitor===analytics_hash('|'.$site['id']))$visitor=analytics_hash($ip.'|'.$site['id']);
    $event=preg_replace('/[^a-zA-Z0-9_.:-]/','',substr((string)($payload['event']??'page_view'),0,80))?:'page_view';$path=substr((string)($payload['path']??'/'),0,500);$title=substr((string)($payload['title']??''),0,220);$ref=substr((string)($payload['referrer']??($_SERVER['HTTP_REFERER']??'')),0,500);
    $meta=$payload['metadata']??[];if(!is_array($meta))$meta=[];$meta=array_slice($meta,0,20,true);foreach($meta as $k=>$v){if(is_scalar($v))$meta[$k]=substr((string)$v,0,500);else unset($meta[$k]);}
    $utm=[];foreach(['source','medium','campaign','term','content'] as $k)$utm[$k]=substr((string)($payload['utm_'.$k]??''),0,190);
    $screenW=(int)($payload['screen_width']??0);$screenH=(int)($payload['screen_height']??0);$lang=substr((string)($payload['language']??''),0,40);$value=is_numeric($payload['value']??null)?(float)$payload['value']:null;
    $geo=analytics_geo($ip);$country=strtoupper(substr((string)($payload['country']??$geo['country']),0,2));$countryName=substr((string)($payload['country_name']??$geo['country_name']??''),0,120);$region=substr((string)($payload['region']??$geo['region']),0,120);$city=substr((string)($payload['city']??$geo['city']),0,120);$latitude=is_numeric($payload['latitude']??null)?(float)$payload['latitude']:($geo['latitude']??null);$longitude=is_numeric($payload['longitude']??null)?(float)$payload['longitude']:($geo['longitude']??null);$timezone=substr((string)($payload['timezone']??$geo['timezone']??''),0,80);$ipHash=analytics_hash($ip.'|'.$site['id']);
    $pdo->beginTransaction();try{
        $pdo->prepare('INSERT INTO analytics_events(site_id,session_hash,visitor_hash,event_name,page_path,page_title,referrer,utm_source,utm_medium,utm_campaign,utm_term,utm_content,country_code,country_name,region,city,latitude,longitude,timezone_name,device_category,browser,browser_version,os,screen_width,screen_height,language,ip_hash,event_value,metadata_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([(int)$site['id'],$session,$visitor,$event,$path,$title,$ref,$utm['source'],$utm['medium'],$utm['campaign'],$utm['term'],$utm['content'],$country?:null,$countryName?:null,$region?:null,$city?:null,$latitude,$longitude,$timezone?:null,$uaInfo['device'],$uaInfo['browser'],$uaInfo['version'],$uaInfo['os'],$screenW?:null,$screenH?:null,$lang?:null,$ipHash,$value,json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$now]);
        $q=$pdo->prepare('SELECT id FROM analytics_sessions WHERE site_id=? AND session_hash=? LIMIT 1');$q->execute([(int)$site['id'],$session]);$existing=$q->fetchColumn();
        if($existing){$pdo->prepare("UPDATE analytics_sessions SET last_seen_at=?,exit_path=?,pageviews=pageviews+?,event_count=event_count+1,engagement_seconds=engagement_seconds+?,converted=GREATEST(converted,?),country_code=COALESCE(NULLIF(?,''),country_code),country_name=COALESCE(NULLIF(?,''),country_name),latitude=COALESCE(?,latitude),longitude=COALESCE(?,longitude),timezone_name=COALESCE(NULLIF(?,''),timezone_name),device_category=COALESCE(NULLIF(?,''),device_category),browser=COALESCE(NULLIF(?,''),browser),os=COALESCE(NULLIF(?,''),os),ip_hash=COALESCE(NULLIF(?,''),ip_hash) WHERE id=?")->execute([$now,$path,$event==='page_view'?1:0,$event==='heartbeat'?30:0,$event==='conversion'?1:0,$country,$countryName,$latitude,$longitude,$timezone,$uaInfo['device'],$uaInfo['browser'],$uaInfo['os'],$ipHash,(int)$existing]);}
        else{$pdo->prepare('INSERT INTO analytics_sessions(site_id,session_hash,visitor_hash,started_at,last_seen_at,landing_path,exit_path,pageviews,event_count,engagement_seconds,converted,referrer,utm_source,utm_medium,utm_campaign,country_code,country_name,latitude,longitude,timezone_name,device_category,browser,os,ip_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([(int)$site['id'],$session,$visitor,$now,$now,$path,$path,$event==='page_view'?1:0,1,$event==='heartbeat'?30:0,$event==='conversion'?1:0,$ref,$utm['source'],$utm['medium'],$utm['campaign'],$country?:null,$countryName?:null,$latitude,$longitude,$timezone?:null,$uaInfo['device'],$uaInfo['browser'],$uaInfo['os'],$ipHash]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return ['ok'=>true,'event'=>$event];
}
function analytics_range(string $range): array { $map=['24h'=>1,'7d'=>7,'30d'=>30,'90d'=>90];$days=$map[$range]??7;return [$days,date('Y-m-d H:i:s',time()-$days*86400),date('Y-m-d H:i:s')]; }
function analytics_report(int $siteId,string $range='7d'): array {
    [$days,$from,$to]=analytics_range($range);$p=db();$site=analytics_get_site($siteId);if(!$site)throw new RuntimeException('Analytics site not found.');$w='site_id=? AND created_at BETWEEN ? AND ?';$args=[$siteId,$from,$to];
    $q=$p->prepare("SELECT COUNT(*) FROM analytics_events WHERE $w AND event_name='page_view'");$q->execute($args);$pageviews=(int)$q->fetchColumn();$q=$p->prepare("SELECT COUNT(DISTINCT session_hash) FROM analytics_sessions WHERE site_id=? AND started_at BETWEEN ? AND ?");$q->execute($args);$sessions=(int)$q->fetchColumn();$q=$p->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM analytics_sessions WHERE site_id=? AND started_at BETWEEN ? AND ?");$q->execute($args);$visitors=(int)$q->fetchColumn();$q=$p->prepare("SELECT COUNT(*) FROM analytics_sessions WHERE site_id=? AND started_at BETWEEN ? AND ? AND pageviews<=1 AND converted=0");$q->execute($args);$bounces=(int)$q->fetchColumn();$bounceRate=$sessions>0?round($bounces/$sessions*100,1):0;$q=$p->prepare("SELECT COUNT(*) FROM analytics_sessions WHERE site_id=? AND last_seen_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)");$q->execute([$siteId]);$realtime=(int)$q->fetchColumn();$q=$p->prepare("SELECT AVG(engagement_seconds),AVG(pageviews),SUM(converted) FROM analytics_sessions WHERE site_id=? AND started_at BETWEEN ? AND ?");$q->execute($args);$agg=$q->fetch()?:[];$conversions=(int)($agg['SUM(converted)']??0);
    $top=function(string $col,int $limit=8)use($p,$w,$args){$q=$p->prepare("SELECT COALESCE(NULLIF($col,''),'(direct)') label,COUNT(*) n FROM analytics_events WHERE $w GROUP BY $col ORDER BY n DESC LIMIT $limit");$q->execute($args);return $q->fetchAll();};
    $pages=$top('page_path');$sources=$top('utm_source');$devices=$top('device_category');$countries=$top('country_code');$browsers=$top('browser');$events=$top('event_name');
    $q=$p->prepare("SELECT DATE(created_at) label,COUNT(*) n FROM analytics_events WHERE $w AND event_name='page_view' GROUP BY DATE(created_at) ORDER BY label");$q->execute($args);$trend=$q->fetchAll();
    $q=$p->prepare("SELECT HOUR(created_at) hour,COUNT(*) n FROM analytics_events WHERE $w GROUP BY HOUR(created_at) ORDER BY hour");$q->execute($args);$hours=$q->fetchAll();
    return compact('site','days','from','to','pageviews','sessions','visitors','bounces','bounceRate','realtime','agg','conversions','pages','sources','devices','countries','browsers','events','trend','hours');
}
