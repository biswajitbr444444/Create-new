<?php
declare(strict_types=1);

function code_schema_sql(): array { return [
"CREATE TABLE IF NOT EXISTS code_snippets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 title VARCHAR(220) NOT NULL,
 language VARCHAR(40) NOT NULL DEFAULT 'text',
 category VARCHAR(100) NULL,
 tags_json TEXT NULL,
 code_text LONGTEXT NOT NULL,
 privacy ENUM('public','unlisted','private') NOT NULL DEFAULT 'unlisted',
 password_hash VARCHAR(255) NULL,
 expires_at DATETIME NULL,
 burn_after_read TINYINT(1) NOT NULL DEFAULT 0,
 views BIGINT UNSIGNED NOT NULL DEFAULT 0,
 token CHAR(32) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 published_at DATETIME NULL,
 author_country VARCHAR(8) NULL,
 author_city VARCHAR(120) NULL,
 status ENUM('active','expired','deleted') NOT NULL DEFAULT 'active',
 INDEX(user_id), INDEX(parent_id), INDEX(category), INDEX(language), INDEX(privacy), INDEX(expires_at), INDEX(views),
 CONSTRAINT fk_code_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_code_parent FOREIGN KEY(parent_id) REFERENCES code_snippets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS code_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 snippet_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 version_no INT UNSIGNED NOT NULL,
 code_text LONGTEXT NOT NULL,
 language VARCHAR(40) NOT NULL,
 title VARCHAR(220) NOT NULL,
 category VARCHAR(100) NULL,
 tags_json TEXT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_code_version(snippet_id,version_no), INDEX(snippet_id), INDEX(user_id),
 CONSTRAINT fk_code_version_snippet FOREIGN KEY(snippet_id) REFERENCES code_snippets(id) ON DELETE CASCADE,
 CONSTRAINT fk_code_version_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS code_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_code_category(user_id,name), INDEX(user_id),
 CONSTRAINT fk_code_category_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS code_access_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 snippet_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('view','share','execute') NOT NULL,
 visitor_hash CHAR(64) NULL,
 user_agent VARCHAR(500) NULL,
 country_code VARCHAR(8) NULL,
 city VARCHAR(120) NULL,
 referrer VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 INDEX(snippet_id), INDEX(event_type), INDEX(created_at),
 CONSTRAINT fk_code_event_snippet FOREIGN KEY(snippet_id) REFERENCES code_snippets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
]; }
function ensure_code_schema(): void {
 static $done=false; if($done)return; $done=true;
 try {
  $pdo=db();
  // Create missing code tables independently so one pre-existing/legacy table cannot
  // prevent the remaining tables from being initialized.
  foreach(code_schema_sql() as $sql){ try{$pdo->exec($sql);}catch(Throwable $e){} }
  // Repair only additive columns commonly missing on older PocketCat installs.
  $additions=[
   'code_snippets'=>[
    'parent_id'=>"ALTER TABLE code_snippets ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER user_id",
    'category'=>"ALTER TABLE code_snippets ADD COLUMN category VARCHAR(100) NULL AFTER language",
    'tags_json'=>"ALTER TABLE code_snippets ADD COLUMN tags_json TEXT NULL AFTER category",
    'privacy'=>"ALTER TABLE code_snippets ADD COLUMN privacy ENUM('public','unlisted','private') NOT NULL DEFAULT 'unlisted' AFTER code_text",
    'password_hash'=>"ALTER TABLE code_snippets ADD COLUMN password_hash VARCHAR(255) NULL AFTER privacy",
    'expires_at'=>"ALTER TABLE code_snippets ADD COLUMN expires_at DATETIME NULL AFTER password_hash",
    'burn_after_read'=>"ALTER TABLE code_snippets ADD COLUMN burn_after_read TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at",
    'views'=>"ALTER TABLE code_snippets ADD COLUMN views BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER burn_after_read",
    'published_at'=>"ALTER TABLE code_snippets ADD COLUMN published_at DATETIME NULL AFTER updated_at",
    'author_country'=>"ALTER TABLE code_snippets ADD COLUMN author_country VARCHAR(8) NULL AFTER published_at",
    'author_city'=>"ALTER TABLE code_snippets ADD COLUMN author_city VARCHAR(120) NULL AFTER author_country",
    'status'=>"ALTER TABLE code_snippets ADD COLUMN status ENUM('active','expired','deleted') NOT NULL DEFAULT 'active' AFTER author_city"
   ],
   'code_versions'=>[
    'category'=>"ALTER TABLE code_versions ADD COLUMN category VARCHAR(100) NULL AFTER title",
    'tags_json'=>"ALTER TABLE code_versions ADD COLUMN tags_json TEXT NULL AFTER category"
   ],
   'code_access_events'=>[
    'visitor_hash'=>"ALTER TABLE code_access_events ADD COLUMN visitor_hash CHAR(64) NULL AFTER event_type",
    'user_agent'=>"ALTER TABLE code_access_events ADD COLUMN user_agent VARCHAR(500) NULL AFTER visitor_hash",
    'country_code'=>"ALTER TABLE code_access_events ADD COLUMN country_code VARCHAR(8) NULL AFTER user_agent",
    'city'=>"ALTER TABLE code_access_events ADD COLUMN city VARCHAR(120) NULL AFTER country_code",
    'referrer'=>"ALTER TABLE code_access_events ADD COLUMN referrer VARCHAR(500) NULL AFTER city"
   ]
  ];
  foreach($additions as $table=>$items){
   foreach($items as $column=>$sql){
    try{
     $q=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
     $q->execute([$table,$column]);
     if(!(int)$q->fetchColumn())$pdo->exec($sql);
    }catch(Throwable $e){}
   }
  }
 } catch(Throwable $e) { /* request layer reports actionable database failures */ }
}
function code_languages(): array { return [
 'text'=>['label'=>'Plain Text','ext'=>'txt','mime'=>'text/plain','prism'=>'none'],
 'html'=>['label'=>'HTML','ext'=>'html','mime'=>'text/html','prism'=>'markup'],
 'css'=>['label'=>'CSS','ext'=>'css','mime'=>'text/css','prism'=>'css'],
 'js'=>['label'=>'JavaScript','ext'=>'js','mime'=>'text/javascript','prism'=>'javascript'],
 'json'=>['label'=>'JSON','ext'=>'json','mime'=>'application/json','prism'=>'json'],
 'py'=>['label'=>'Python','ext'=>'py','mime'=>'text/x-python','prism'=>'python'],
 'c'=>['label'=>'C','ext'=>'c','mime'=>'text/x-c','prism'=>'c'],
 'cpp'=>['label'=>'C++','ext'=>'cpp','mime'=>'text/x-c++src','prism'=>'cpp'],
 'cs'=>['label'=>'C#','ext'=>'cs','mime'=>'text/plain','prism'=>'csharp'],
 'java'=>['label'=>'Java','ext'=>'java','mime'=>'text/x-java-source','prism'=>'java'],
 'lua'=>['label'=>'Lua','ext'=>'lua','mime'=>'text/x-lua','prism'=>'lua'],
 'md'=>['label'=>'Markdown','ext'=>'md','mime'=>'text/markdown','prism'=>'markdown'],
 'php'=>['label'=>'PHP','ext'=>'php','mime'=>'application/x-httpd-php','prism'=>'php'],
 'rb'=>['label'=>'Ruby','ext'=>'rb','mime'=>'text/x-ruby','prism'=>'ruby'],
 'sql'=>['label'=>'SQL','ext'=>'sql','mime'=>'application/sql','prism'=>'sql'],
 'xml'=>['label'=>'XML','ext'=>'xml','mime'=>'application/xml','prism'=>'markup'],
 'sh'=>['label'=>'Shell','ext'=>'sh','mime'=>'application/x-sh','prism'=>'bash'],
 'm'=>['label'=>'Objective-C','ext'=>'m','mime'=>'text/x-objective-c','prism'=>'objectivec']
]; }
function code_language(string $k): array { $all=code_languages(); return $all[$k]??$all['text']; }
function code_token(): string { do{$t=bin2hex(random_bytes(16));$q=db()->prepare('SELECT 1 FROM code_snippets WHERE token=?');$q->execute([$t]);}while($q->fetchColumn());return $t; }
function code_author_location(): array { $cc=strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY']??''),0,8));$city=trim((string)($_SERVER['HTTP_CF_IPCITY']??''));return [$cc?:null,$city!==''?substr($city,0,120):null]; }
function code_tags_normalize($tags): array { if(is_string($tags))$tags=preg_split('/[,\n]+/',$tags);$out=[];foreach((array)$tags as $t){$t=trim((string)$t);if($t!==''&&!in_array($t,$out,true))$out[]=substr($t,0,40);if(count($out)>=20)break;}return $out; }
function code_expiry_from_choice(string $choice): ?string { $map=['never'=>null,'10m'=>'+10 minutes','1h'=>'+1 hour','1d'=>'+1 day','1w'=>'+1 week','2w'=>'+2 weeks','1mo'=>'+1 month','6mo'=>'+6 months','1y'=>'+1 year']; if($choice==='burn')return date('Y-m-d H:i:s',time()+315360000); if(!array_key_exists($choice,$map))return null; return $map[$choice]===null?null:date('Y-m-d H:i:s',strtotime($map[$choice])); }
function code_is_expired(array $s): bool { return ($s['status']??'active')!=='active' || (!empty($s['expires_at']) && strtotime((string)$s['expires_at'])<=time()); }
function code_expire_due(): void { try{db()->exec("UPDATE code_snippets SET status='expired',updated_at=NOW() WHERE status='active' AND expires_at IS NOT NULL AND expires_at<=NOW()");}catch(Throwable $e){} }
function code_mark_expired(int $id): void { try{db()->prepare("UPDATE code_snippets SET status='expired',updated_at=NOW() WHERE id=? AND status='active'")->execute([$id]);}catch(Throwable $e){} }
function code_public_url(string $token): string { return rtrim((string)(app_config()['app_url']??''),'/').'/code/'.rawurlencode($token); }
function code_event(int $id,string $type): void { try{ $hash=hash_hmac('sha256',client_ip(),app_secret());$q=db()->prepare('INSERT INTO code_access_events(snippet_id,event_type,visitor_hash,user_agent,country_code,city,referrer,created_at) VALUES(?,?,?,?,?,?,?,NOW())');$q->execute([$id,$type,$hash,substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),substr((string)($_SERVER['HTTP_CF_IPCOUNTRY']??''),0,8),substr((string)($_SERVER['HTTP_CF_IPCITY']??''),0,120),substr((string)($_SERVER['HTTP_REFERER']??''),0,500)]); }catch(Throwable $e){} }
function code_require_owner(int $id): array { require_login();$q=db()->prepare('SELECT * FROM code_snippets WHERE id=? AND user_id=? LIMIT 1');$q->execute([$id,(int)current_user()['id']]);$s=$q->fetch();if(!$s)json_response(['error'=>'not_found'],404);return $s; }
function code_allowed_to_create(): bool { return policy_flag('url_engine',true); }
