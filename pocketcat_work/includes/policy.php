<?php
declare(strict_types=1);

/** Testing mode is an explicit administrator-controlled setting and defaults OFF. */
function policy_testing_mode(): bool {
    return (string)(setting('testing_mode_enabled', '0') ?? '0') === '1';
}
function policy_defaults(): array {
    return [
        'version'=>2,
        'testing_mode'=>false,
        'plan_meta'=>[
            'free'=>['name'=>'Free','price'=>'0','period'=>'month','enabled'=>true,'description'=>'Start with the essentials.'],
            'basic'=>['name'=>'Basic','price'=>'3','period'=>'month','enabled'=>true,'description'=>'More room for everyday work.'],
            'premium'=>['name'=>'Premium','price'=>'6','period'=>'month','enabled'=>true,'description'=>'Advanced tools and higher limits.'],
        ],
        'plans'=>[
            'free'=>[
                'tasks'=>10,'projects'=>10,'notes'=>10,'ai_seconds'=>7200,
                'url_monthly_links'=>50,'url_redirect_mode'=>'301','url_analytics_days'=>30,'url_custom_alias'=>false,'url_password'=>false,'url_geo_os'=>false,'url_geo_country'=>false,'url_geo_city'=>false,'url_deep_link'=>false,'url_pixels'=>false,'url_api'=>false,'url_webhooks'=>false,'url_bulk_csv'=>false,'url_heatmap'=>false,'advanced_analytics'=>false,'website_tracking'=>false,'analytics_retention_days'=>30,
                'file_max_bytes'=>100*1024*1024,'file_storage_bytes'=>1*1024*1024*1024,'file_max_expiry_hours'=>24,'file_password'=>false,'file_burn'=>false,'file_preview'=>false,'file_custom_qr'=>false,'file_direct_download'=>false,'file_resumable'=>false,'file_permanent'=>false,'file_cdn'=>false,'file_folder_upload'=>false,'file_zip_downloads'=>false,'file_public_share'=>true,'file_email_share'=>false,'file_view_only'=>true,
                'qr_watermark'=>true,'ads_on_download'=>true,'api_rpm'=>0,'api_rph'=>0,
                'code_max_chars'=>10000,'code_max_versions'=>10,'code_history_days'=>7,'code_max_expiry_hours'=>24,'code_password'=>false,'code_burn'=>false,'code_preview_runs_per_hour'=>5,'code_export_all'=>false,'code_watermark'=>true,'code_private'=>false,'code_api'=>false,'code_priority_worker'=>false
            ],
            'basic'=>[
                'tasks'=>30,'projects'=>30,'notes'=>30,'ai_seconds'=>21600,
                'url_monthly_links'=>500,'url_redirect_mode'=>'301','url_analytics_days'=>90,'url_custom_alias'=>true,'url_password'=>true,'url_geo_os'=>true,'url_geo_country'=>true,'url_geo_city'=>false,'url_deep_link'=>false,'url_pixels'=>false,'url_api'=>false,'url_webhooks'=>false,'url_bulk_csv'=>true,'url_heatmap'=>false,'advanced_analytics'=>false,'website_tracking'=>false,'analytics_retention_days'=>30,
                'file_max_bytes'=>2*1024*1024*1024,'file_storage_bytes'=>25*1024*1024*1024,'file_max_expiry_hours'=>14*24,'file_password'=>true,'file_burn'=>false,'file_preview'=>true,'file_custom_qr'=>true,'file_direct_download'=>true,'file_resumable'=>false,'file_permanent'=>false,'file_cdn'=>false,'file_folder_upload'=>true,'file_zip_downloads'=>true,'file_public_share'=>true,'file_email_share'=>true,'file_view_only'=>true,
                'qr_watermark'=>false,'ads_on_download'=>false,'api_rpm'=>0,'api_rph'=>0,
                'code_max_chars'=>100000,'code_max_versions'=>100,'code_history_days'=>90,'code_max_expiry_hours'=>30*24,'code_password'=>true,'code_burn'=>true,'code_preview_runs_per_hour'=>0,'code_export_all'=>true,'code_watermark'=>false,'code_private'=>false,'code_api'=>false,'code_priority_worker'=>false
            ],
            'premium'=>[
                'tasks'=>0,'projects'=>0,'notes'=>0,'ai_seconds'=>0,
                'url_monthly_links'=>0,'url_redirect_mode'=>'301','url_analytics_days'=>0,'url_custom_alias'=>true,'url_password'=>true,'url_geo_os'=>true,'url_geo_country'=>true,'url_geo_city'=>true,'url_deep_link'=>true,'url_pixels'=>true,'url_api'=>true,'url_webhooks'=>true,'url_bulk_csv'=>true,'url_heatmap'=>true,'advanced_analytics'=>true,'website_tracking'=>true,'analytics_retention_days'=>365,
                'file_max_bytes'=>20*1024*1024*1024,'file_storage_bytes'=>1024*1024*1024*1024,'file_max_expiry_hours'=>0,'file_password'=>true,'file_burn'=>true,'file_preview'=>true,'file_custom_qr'=>true,'file_direct_download'=>true,'file_resumable'=>true,'file_permanent'=>true,'file_cdn'=>true,'file_folder_upload'=>true,'file_zip_downloads'=>true,'file_public_share'=>true,'file_email_share'=>true,'file_view_only'=>true,
                'qr_watermark'=>false,'ads_on_download'=>false,'api_rpm'=>1200,'api_rph'=>30000,
                'code_max_chars'=>0,'code_max_versions'=>0,'code_history_days'=>0,'code_max_expiry_hours'=>0,'code_password'=>true,'code_burn'=>true,'code_preview_runs_per_hour'=>0,'code_export_all'=>true,'code_watermark'=>false,'code_private'=>true,'code_api'=>true,'code_priority_worker'=>true
            ]
        ],
        'flags'=>['visual_editor'=>true,'visual_custom_js'=>false,'visual_runtime'=>true,'visual_scheduling'=>true,
            'allow_user_byod'=>true,
            'url_engine'=>true,'url_create'=>true,'url_redirects'=>true,'file_engine'=>true,'file_uploads'=>true,'file_downloads'=>true,'file_previews'=>true,'file_folder_upload'=>true,'file_zip_downloads'=>true,'file_public_share'=>true,'file_email_share'=>true,'api'=>true,'webhooks'=>true,'qr'=>true,'maintenance_mode'=>false
        ],
        'global'=>[
            'redirect_rate_per_minute'=>240,'code_pastes_per_minute'=>20,'code_preview_per_hour'=>30,'code_default_language'=>'text','code_default_font_size'=>14,'code_tab_size'=>4,'code_line_numbers'=>true,'code_block_keywords'=>['<script','javascript:','<iframe','onerror=','onload=','eval(','document.cookie','powershell','cmd.exe'],'code_author_country_override'=>'','code_author_city_override'=>'','api_rate_per_minute'=>120,'api_rate_per_hour'=>5000,'upload_concurrency'=>3,'upload_part_mb'=>100,'download_speed_mbps'=>0,'suspicious_ip_throttle_mbps'=>1,'analytics_retention_days'=>365,'file_log_retention_days'=>90,'link_log_retention_days'=>365,
            'blocked_extensions'=>['exe','bat','cmd','sh','bash','vbs','vbe','js','jar','msi','scr','com','ps1','apk'],'blocked_mimes'=>['application/x-msdownload','application/x-dosexec','application/vnd.android.package-archive'],'blocked_cidrs'=>[],'blocked_countries'=>[]
        ],
        'code_theme'=>['accent'=>'#6557e8','button_gradient_start'=>'#6557e8','button_gradient_end'=>'#8b7cf6','light_bg'=>'#f5f7fb','dark_bg'=>'#0a1020','font_family'=>'system-ui'],
        'branding'=>['name'=>'PocketCat','logo'=>'','favicon'=>'','support_email'=>'','og_title'=>'PocketCat','og_description'=>'Secure links and ephemeral file transfer.','og_image'=>''],
    ];
}
/** Maximum accepted integer for a plan allocation. Byte quotas support up to 2 TiB. */
function policy_allocation_max(string $feature): int {
    if (in_array($feature, ['file_storage_bytes', 'file_max_bytes'], true)) {
        return 2199023255552; // 2 TiB; safely below PHP_INT_MAX on supported 64-bit deployments.
    }
    return 1000000000000;
}

/** Validate and normalize a numeric plan allocation without silently truncating decimals. */
function policy_validate_numeric_allocation(string $feature, mixed $value): int {
    if (is_bool($value) || is_array($value) || is_object($value) || $value === null) {
        throw new InvalidArgumentException("Invalid non-negative allocation {$feature}.");
    }
    $raw = trim((string)$value);
    $number = filter_var($raw, FILTER_VALIDATE_INT);
    if ($number === false || $number < 0 || $number > policy_allocation_max($feature)) {
        throw new InvalidArgumentException("Invalid non-negative allocation {$feature}; maximum allowed is " . policy_allocation_max($feature) . '.');
    }
    return (int)$number;
}

function policy_data(): array {
    if(isset($GLOBALS['_policy_data_cache']) && is_array($GLOBALS['_policy_data_cache'])) return $GLOBALS['_policy_data_cache'];
    $base=policy_defaults();
    $raw=setting('policy_engine','');
    $saved=json_decode((string)$raw,true);
    $data=is_array($saved) ? array_replace_recursive($base,$saved) : $base;
    $GLOBALS['_policy_data_cache']=$data;
    return $data;
}
function policy_save(array $data, ?int $adminId=null, string $action='policy.update'): void {
    $before=policy_data();
    set_setting('policy_engine',json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    set_setting('policy_engine_updated_at',gmdate('c'));
    $GLOBALS['_policy_cache']=$data;
    $GLOBALS['_policy_data_cache']=array_replace_recursive(policy_defaults(),$data);
    try{
        db()->prepare('INSERT INTO policy_audit_logs(admin_id,action,before_json,after_json,ip_address,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$adminId,$action,json_encode($before),json_encode($data),client_ip()]);
    }catch(Throwable $e){}
}
function policy_plan(string $plan): array { $d=policy_data(); $meta=$d['plan_meta'][$plan]??[]; if(!in_array($plan,['free','basic','premium'],true) || (isset($meta['enabled']) && !$meta['enabled'])) $plan='free'; $p=$d['plans'][$plan]??$d['plans']['free']; return policy_testing_mode()?policy_testing_unlimited($p):$p; }
function policy_override_map(int $uid): array {
    try{$s=db()->prepare('SELECT policy_key,policy_value FROM policy_user_overrides WHERE user_id=? AND (expires_at IS NULL OR expires_at>NOW())');$s->execute([$uid]);$out=[];foreach($s->fetchAll() as $r){$v=json_decode((string)$r['policy_value'],true);$out[(string)$r['policy_key']]=$v===null?(string)$r['policy_value']:$v;}return $out;}catch(Throwable $e){return [];}
}
function policy_testing_unlimited(array $policy): array {
    $booleanKeys=[
        'url_custom_alias','url_password','url_geo_os','url_geo_country','url_geo_city','url_deep_link','url_pixels','url_api','url_webhooks','url_bulk_csv','url_heatmap','advanced_analytics','website_tracking',
        'file_password','file_burn','file_preview','file_custom_qr','file_direct_download','file_resumable','file_permanent','file_cdn','file_folder_upload','file_zip_downloads','file_public_share','file_email_share','file_view_only',
        'code_password','code_burn','code_export_all','code_private','code_api','code_priority_worker'
    ];
    foreach($booleanKeys as $k)$policy[$k]=true;
    foreach(['tasks','projects','notes','ai_seconds','url_monthly_links','url_analytics_days','analytics_retention_days','file_max_bytes','file_storage_bytes','file_max_expiry_hours','code_max_chars','code_max_versions','code_history_days','code_max_expiry_hours','code_preview_runs_per_hour'] as $k)$policy[$k]=0;
    $policy['qr_watermark']=false;$policy['ads_on_download']=false;
    return $policy;
}
function policy_effective(int $uid): array {
    static $cache=[]; if(isset($cache[$uid]))return $cache[$uid];
    $plan='free';try{$s=db()->prepare('SELECT plan FROM users WHERE id=?');$s->execute([$uid]);$plan=(string)($s->fetchColumn()?:'free');}catch(Throwable $e){}
    $meta=policy_data()['plan_meta'][$plan]??[]; if(isset($meta['enabled']) && !$meta['enabled']) $plan='free';
    $effective=policy_plan($plan);foreach(policy_override_map($uid) as $k=>$v)$effective[$k]=$v;
    if(policy_testing_mode())$effective=policy_testing_unlimited($effective);
    $cache[$uid]=$effective;return $effective;
}
function policy_allows(int $uid,string $key,bool $default=false): bool { $v=policy_effective($uid)[$key]??$default;return filter_var($v,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE) ?? ((int)$v===1); }
function policy_value(int $uid,string $key,$default=null){return policy_effective($uid)[$key]??$default;}
function policy_flag(string $key,bool $default=false): bool { if(policy_testing_mode() && $key!=='maintenance_mode') return true; $v=policy_data()['flags'][$key]??$default;return filter_var($v,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE) ?? ((int)$v===1); }
function policy_global(string $key,$default=null){return policy_data()['global'][$key]??$default;}
function policy_require_flag(string $key): void { if(!policy_flag($key,false)){http_response_code(503);exit('This feature is temporarily unavailable.');} }
function policy_user_can_create_link(int $uid): array {
    if(!policy_flag('url_create',true)||!policy_flag('url_engine',true))return [false,'URL creation is temporarily disabled by the administrator.'];
    $limit=(int)policy_value($uid,'url_monthly_links',50);if($limit<=0)return [true,'OK'];
    $s=db()->prepare("SELECT COUNT(*) FROM short_links WHERE user_id=? AND created_at>=DATE_FORMAT(NOW(),'%Y-%m-01 00:00:00') AND status<>'disabled'");$s->execute([$uid]);$n=(int)$s->fetchColumn();return $n>=$limit?[false,'Monthly URL link limit reached.']:[true,'OK'];
}
function policy_assert_link_feature(int $uid,string $feature): void { if(!policy_allows($uid,$feature,false))throw new RuntimeException('This feature is not available on your current plan.'); }
function policy_validate_file_expiry(int $uid, ?string $expires): ?string {
    if(!$expires)return null;
    $t=strtotime($expires);if(!$t)throw new InvalidArgumentException('Invalid expiry date.');
    $hours=(int)policy_value($uid,'file_max_expiry_hours',24);if($hours>0 && $t>time()+$hours*3600)throw new RuntimeException('Selected expiry exceeds your plan limit.');
    return date('Y-m-d H:i:s',$t);
}
function policy_validate_file_options(int $uid,string $password,int $burn): void {
    if($password!==''&&!policy_allows($uid,'file_password',false))throw new RuntimeException('Password protection is not available on your plan.');
    if($burn&&!policy_allows($uid,'file_burn',false))throw new RuntimeException('Burn After Reading is a Premium feature.');
}
function policy_audit(int $adminId,string $action,array $before=[],array $after=[]): void {try{db()->prepare('INSERT INTO policy_audit_logs(admin_id,action,before_json,after_json,ip_address,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$adminId,$action,json_encode($before),json_encode($after),client_ip()]);}catch(Throwable $e){}}
function policy_impersonate(int $adminId,int $targetId): void {
    if($adminId===$targetId)throw new RuntimeException('Cannot impersonate yourself.');
    $s=db()->prepare("SELECT id FROM users WHERE id=? AND status='active'");$s->execute([$targetId]);if(!$s->fetchColumn())throw new RuntimeException('Target user is unavailable.');
    $_SESSION['impersonator_id']=$adminId;$_SESSION['impersonated_user_id']=$targetId;$_SESSION['impersonation_started']=time();
    db()->prepare('INSERT INTO admin_impersonation_logs(admin_id,target_user_id,action,ip_address,created_at) VALUES(?,?,?,?,NOW())')->execute([$adminId,$targetId,'start',client_ip()]);
}
function policy_stop_impersonation(): void { $admin=(int)($_SESSION['impersonator_id']??0);$target=(int)($_SESSION['impersonated_user_id']??0);if($admin&&$target){try{db()->prepare('INSERT INTO admin_impersonation_logs(admin_id,target_user_id,action,ip_address,created_at) VALUES(?,?,?,?,NOW())')->execute([$admin,$target,'stop',client_ip()]);}catch(Throwable $e){}}unset($_SESSION['impersonator_id'],$_SESSION['impersonated_user_id'],$_SESSION['impersonation_started']);}
function policy_admin_permission(string $permission): bool {
    $u=current_user();if(!$u||($u['role']??'')!=='admin')return false;
    $roles=[];try{$s=db()->prepare('SELECT r.permissions_json FROM admin_roles r JOIN user_admin_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');$s->execute([(int)$u['id']]);$roles=$s->fetchAll();}catch(Throwable $e){}
    if(!$roles)return true;
    foreach($roles as $r){$p=json_decode((string)$r['permissions_json'],true);if(is_array($p)&&(!empty($p['*'])||!empty($p[$permission])))return true;}return false;
}
function policy_request_blocked(): bool {
    $ip=client_ip();$country=strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY']??''),0,2));
    try{
        $s=db()->query("SELECT rule_type,value,action FROM global_block_rules WHERE active=1");
        foreach($s->fetchAll() as $r){$match=false;$type=$r['rule_type'];$v=(string)$r['value'];
            if($type==='ip'&&hash_equals($v,$ip))$match=true;
            elseif($type==='country'&&strtoupper($v)===$country)$match=true;
            elseif($type==='cidr'&&function_exists('inet_pton')){$ipb=inet_pton($ip);$parts=explode('/',$v,2);$net=inet_pton($parts[0]??'');$bits=(int)($parts[1]??0);if($ipb!==false&&$net!==false&&$bits>=0&&$bits<=128){$bytes=intdiv($bits,8);$rem=$bits%8;if(substr($ipb,0,$bytes)!==substr($net,0,$bytes))$match=false;else $match=$rem===0 || ((ord($ipb[$bytes]) & (0xFF << (8-$rem)))===(ord($net[$bytes]) & (0xFF << (8-$rem))));}}
            if($match&&$type!=='user'&&$r['action']==='block')return true;
        }
    }catch(Throwable $e){}
    return false;
}
function policy_rate_limit(string $bucket,int $fallback=120): bool { $limit=(int)policy_global($bucket==='api'? 'api_rate_per_minute':'redirect_rate_per_minute',$fallback);return link_rate_limit($bucket.':'.client_ip(),max(1,$limit)); }

function policy_code_rate_limit(int $uid): bool {
 $per=(int)policy_global('code_pastes_per_minute',20); if($per<=0)return true;
 return link_rate_limit('code:'.(int)$uid.':'.client_ip(),$per);
}
function policy_code(int $uid): array { return [
 'max_chars'=>(int)policy_value($uid,'code_max_chars',10000),
 'max_versions'=>(int)policy_value($uid,'code_max_versions',10),
 'history_days'=>(int)policy_value($uid,'code_history_days',7),
 'max_expiry_hours'=>(int)policy_value($uid,'code_max_expiry_hours',24),
 'password'=>policy_allows($uid,'code_password',false),
 'burn'=>policy_allows($uid,'code_burn',false),
 'preview_runs_per_hour'=>(int)policy_value($uid,'code_preview_runs_per_hour',5),
 'export_all'=>policy_allows($uid,'code_export_all',false),
 'watermark'=>policy_allows($uid,'code_watermark',true),
 'private'=>policy_allows($uid,'code_private',false),
 'api'=>policy_allows($uid,'code_api',false),
 'priority_worker'=>policy_allows($uid,'code_priority_worker',false)
];}
function policy_validate_code(int $uid,string $code, string $expiry, bool $password, bool $burn, string $privacy): void {
 $p=policy_code($uid); $n=function_exists('mb_strlen')?mb_strlen($code,'UTF-8'):strlen($code);
 if($p['max_chars']>0 && $n>$p['max_chars']) throw new RuntimeException('Code exceeds your plan character limit.');
 if($password&&!$p['password']) throw new RuntimeException('Password protection is not available on your plan.');
 if($burn&&!$p['burn']) throw new RuntimeException('Burn after read is not available on your plan.');
 if($privacy==='private'&&!$p['private']) throw new RuntimeException('Private snippets are not available on your plan.');
 if($expiry==='never' && $p['max_expiry_hours']>0) throw new RuntimeException('Your plan requires an expiry within the allowed window.');
 if($expiry==='burn' && !$p['burn']) throw new RuntimeException('Burn after read is not available on your plan.');
}
function policy_code_expiry(string $choice, int $uid, ?string $custom=null): ?string {
 $p=policy_code($uid); if($choice==='burn') return date('Y-m-d H:i:s',time()+60);
 if($choice==='never'){if($p['max_expiry_hours']>0)return date('Y-m-d H:i:s',time()+$p['max_expiry_hours']*3600);return null;}
 if($custom!==null&&trim($custom)!==''){ $t=strtotime($custom);if(!$t)throw new RuntimeException('Invalid expiration date.');}
 else { $map=['10m'=>600,'1h'=>3600,'1d'=>86400,'1w'=>604800,'1mo'=>2592000,'1y'=>31536000];$t=time()+($map[$choice]??86400); }
 if($p['max_expiry_hours']>0 && $t>time()+$p['max_expiry_hours']*3600)$t=time()+$p['max_expiry_hours']*3600;
 return date('Y-m-d H:i:s',$t);
}
function policy_code_blocked(int $uid,string $code): ?string {
 $keys=(array)policy_global('code_block_keywords',[]);
 foreach($keys as $k) if($k!=='' && stripos($code,(string)$k)!==false)return (string)$k;
 return null;
}
