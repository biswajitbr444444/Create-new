<?php
declare(strict_types=1);
function docs_schema(): void {
    static $done=false; if($done || !function_exists('is_installed') || !is_installed()) return;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS documentation_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(180) NOT NULL UNIQUE,
            title VARCHAR(220) NOT NULL,
            summary VARCHAR(500) NOT NULL,
            section VARCHAR(120) NOT NULL DEFAULT 'General',
            overview LONGTEXT NOT NULL,
            requirements LONGTEXT NOT NULL,
            steps_json LONGTEXT NOT NULL,
            examples_html LONGTEXT NOT NULL,
            troubleshooting_json LONGTEXT NOT NULL,
            official_json LONGTEXT NOT NULL,
            visibility ENUM('private','shared') NOT NULL DEFAULT 'private',
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            INDEX(section), INDEX(updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $count=(int)db()->query('SELECT COUNT(*) FROM documentation_entries')->fetchColumn();
        if($count===0) docs_seed(); else docs_seed_missing();
        $done=true;
    } catch(Throwable $e) {}
}
function docs_seed_data(): array { static $data=null; if($data===null)$data=require __DIR__.'/../docs/seed.php'; return $data; }
function docs_seed(bool $missingOnly=false): void {
    $data=docs_seed_data(); $pdo=db();
    foreach($data['docs'] as $d){$key=$d[0];$x=$data['details'][$key]??[];$steps=$x['Steps']??[];$tr=$x['Troubleshooting']??[];$official=$x['Official']??[];
        if($missingOnly){$q=$pdo->prepare('SELECT id FROM documentation_entries WHERE slug=? LIMIT 1');$q->execute([$key]);if($q->fetchColumn())continue;}
        $st=$pdo->prepare('INSERT INTO documentation_entries(slug,title,summary,section,overview,requirements,steps_json,examples_html,troubleshooting_json,official_json,visibility,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),summary=VALUES(summary),section=VALUES(section)');
        $st->execute([$key,$d[1],$d[2],$d[3],(string)($x['Overview']??''),(string)($x['Requirements']??''),json_encode(array_values($steps),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($x['Examples']??''),json_encode(array_values($tr),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode(array_values($official),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'private']);
    }
}
function docs_seed_missing(): void { docs_seed(true); }
function docs_clean_html(string $html): string { return strip_tags($html,'<p><br><strong><b><em><i><code><pre><ul><ol><li><a>'); }
function docs_get_all(): array { docs_schema(); $st=db()->query("SELECT * FROM documentation_entries WHERE visibility IN ('private','shared') ORDER BY section,title"); return $st?$st->fetchAll():[]; }
function docs_get(string $slug): ?array { docs_schema(); $st=db()->prepare('SELECT * FROM documentation_entries WHERE slug=? LIMIT 1');$st->execute([$slug]);$r=$st->fetch();return $r?:null; }
function docs_get_by_id(int $id): ?array { docs_schema(); $st=db()->prepare('SELECT * FROM documentation_entries WHERE id=? LIMIT 1');$st->execute([$id]);$r=$st->fetch();return $r?:null; }
function docs_decode_list(?string $json): array { $v=json_decode((string)$json,true);return is_array($v)?array_values(array_filter($v,'is_string')):[]; }
function docs_decode_official(?string $json): array { $v=json_decode((string)$json,true);return is_array($v)?$v:[]; }
function docs_steps_text(string $json): string { return implode("\n",docs_decode_list($json)); }
function docs_official_text(string $json): string { $out=[];foreach(docs_decode_official($json) as $x){if(is_array($x)&&isset($x[0],$x[1]))$out[]=$x[0].' | '.$x[1];}return implode("\n",$out); }
function docs_parse_official(string $text): array { $out=[];foreach(preg_split('/\R+/',trim($text)) as $line){$parts=explode('|',$line,2);if(count($parts)!==2)continue;$label=trim($parts[0]);$url=trim($parts[1]);if($label && filter_var($url,FILTER_VALIDATE_URL))$out[]=[$label,$url];}return $out; }
function docs_save(array $in, ?int $id=null): int {
    docs_schema(); $slug=preg_replace('/[^a-z0-9_-]+/i','-',strtolower(trim((string)($in['slug']??''))));$slug=trim($slug,'-');if($slug==='')throw new RuntimeException('Documentation slug is required.');
    $title=trim((string)($in['title']??''));$summary=trim((string)($in['summary']??''));$section=trim((string)($in['section']??'General'))?:'General';if($title==='')throw new RuntimeException('Documentation title is required.');
    $overview=trim((string)($in['overview']??''));$requirements=trim((string)($in['requirements']??''));
    $stepLines=preg_split('/\R+/',(string)($in['steps']??''));$steps=array_values(array_filter(array_map('trim',$stepLines)));
    $trLines=preg_split('/\R+/',(string)($in['troubleshooting']??''));$tr=array_values(array_filter(array_map('trim',$trLines)));
    $examples=docs_clean_html((string)($in['examples']??''));$official=docs_parse_official((string)($in['official']??''));$visibility='private';
    if($id){$st=db()->prepare('UPDATE documentation_entries SET slug=?,title=?,summary=?,section=?,overview=?,requirements=?,steps_json=?,examples_html=?,troubleshooting_json=?,official_json=?,visibility=?,updated_by=?,updated_at=NOW() WHERE id=?');$st->execute([$slug,$title,$summary,$section,$overview,$requirements,json_encode($steps,JSON_UNESCAPED_UNICODE),$examples,json_encode($tr,JSON_UNESCAPED_UNICODE),json_encode($official,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$visibility,(int)current_user()['id'],$id]);return $id;}
    $st=db()->prepare('INSERT INTO documentation_entries(slug,title,summary,section,overview,requirements,steps_json,examples_html,troubleshooting_json,official_json,visibility,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())');$st->execute([$slug,$title,$summary,$section,$overview,$requirements,json_encode($steps,JSON_UNESCAPED_UNICODE),$examples,json_encode($tr,JSON_UNESCAPED_UNICODE),json_encode($official,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$visibility,(int)current_user()['id']]);return (int)db()->lastInsertId();
}
function docs_delete(int $id): void { docs_schema();db()->prepare('DELETE FROM documentation_entries WHERE id=?')->execute([$id]); }
