<?php
declare(strict_types=1);
function ensure_visual_schema(): void {
    static $done=false; if($done || !is_installed()) return; $done=true;
    try { $pdo=db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_versions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,version_number INT UNSIGNED NOT NULL,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,parent_id BIGINT UNSIGNED NULL,author_id BIGINT UNSIGNED NULL,status ENUM('draft','published','scheduled','rolled_back') NOT NULL DEFAULT 'draft',configuration_json LONGTEXT NOT NULL,configuration_hash CHAR(64) NOT NULL,description VARCHAR(255) NULL,scheduled_at DATETIME NULL,created_at DATETIME NOT NULL,published_at DATETIME NULL,INDEX(tenant_id,status),INDEX(scheduled_at),UNIQUE KEY uq_visual_version(tenant_id,version_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_mutations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,version_id BIGINT UNSIGNED NULL,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,node_id VARCHAR(190) NOT NULL,property VARCHAR(100) NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,breakpoint VARCHAR(30) NOT NULL DEFAULT 'base',created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,INDEX(version_id),INDEX(tenant_id,node_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_drafts(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,user_id BIGINT UNSIGNED NOT NULL,base_version_id BIGINT UNSIGNED NULL,session_key CHAR(64) NOT NULL UNIQUE,configuration_json LONGTEXT NOT NULL,updated_at DATETIME NOT NULL,created_at DATETIME NOT NULL,INDEX(tenant_id,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_schedules(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,version_id BIGINT UNSIGNED NOT NULL,run_at DATETIME NOT NULL,status ENUM('pending','published','cancelled','failed') NOT NULL DEFAULT 'pending',created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,INDEX(run_at,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_publish_events(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,version_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(60) NOT NULL,actor_id BIGINT UNSIGNED NULL,request_id CHAR(36) NULL,created_at DATETIME NOT NULL,INDEX(tenant_id),INDEX(version_id),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS visual_design_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,token_key VARCHAR(120) NOT NULL,token_value VARCHAR(500) NOT NULL,updated_by BIGINT UNSIGNED NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_visual_token(tenant_id,token_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Throwable $e) {}
}
function visual_tenant_id(): int { return 0; }
function visual_default_config(): array { return ['version'=>1,'nodes'=>[],'tokens'=>['brand.primary'=>setting('theme_brand','#2563eb'),'brand.secondary'=>setting('theme_brand2','#7c3aed'),'surface.background'=>setting('theme_bg','#f6f8fc'),'surface.card'=>setting('theme_card','#fff'),'text.primary'=>setting('theme_ink','#122033'),'text.muted'=>setting('theme_muted','#68768a')],'global'=>['theme_mode'=>setting('theme_mode','light')]]; }
function visual_active(): array {
    ensure_visual_schema(); $t=visual_tenant_id();
    try{$s=db()->prepare("SELECT * FROM visual_versions WHERE tenant_id=? AND status='published' ORDER BY version_number DESC LIMIT 1");$s->execute([$t]);$r=$s->fetch();if($r){$c=json_decode((string)$r['configuration_json'],true);if(is_array($c))return ['version'=>(int)$r['version_number'],'version_id'=>(int)$r['id'],'config'=>$c];}}catch(Throwable $e){}
    return ['version'=>0,'version_id'=>null,'config'=>visual_default_config()];
}
function visual_audit(string $event, ?int $versionId=null): void { try{db()->prepare('INSERT INTO visual_publish_events(tenant_id,version_id,event_type,actor_id,request_id,created_at) VALUES(?,?,?,?,?,NOW())')->execute([visual_tenant_id(),$versionId,$event,(int)(current_user()['id']??0),function_exists('random_uuid')?random_uuid():bin2hex(random_bytes(16))]);}catch(Throwable $e){} }
function visual_validate_config(array $cfg): array {
    $out=['version'=>1,'nodes'=>[],'tokens'=>[],'global'=>[]];
    foreach(['version','nodes','tokens','global'] as $k) if(array_key_exists($k,$cfg))$out[$k]=$cfg[$k];
    if(!is_array($out['nodes'])||!is_array($out['tokens'])||!is_array($out['global']))throw new RuntimeException('Invalid visual configuration.');
    $cleanNodes=[];
    foreach($out['nodes'] as $node=>$props){$node=(string)$node;if(!preg_match('/^[A-Za-z0-9._:-]{1,190}$/',$node))continue;if(!is_array($props))continue;$clean=[];
        foreach($props as $prop=>$value){$prop=(string)$prop;if(!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,80}$/',$prop))continue;if(is_string($value)&&strlen($value)>5000)throw new RuntimeException('Visual property too large.');$clean[$prop]=$value;}
        $cleanNodes[$node]=$clean;
    }
    $out['nodes']=$cleanNodes;
    foreach($out['tokens'] as $k=>$v){if(!preg_match('/^[A-Za-z0-9._-]{1,120}$/',(string)$k))continue;$out['tokens'][(string)$k]=substr((string)$v,0,500);}
    return $out;
}
function visual_hash(array $cfg): string { return hash('sha256',json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION)); }
