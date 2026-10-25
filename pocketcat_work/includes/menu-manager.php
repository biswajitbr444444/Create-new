<?php
declare(strict_types=1);

function menu_schema(): void {
    static $done=false;
    if($done)return;
    // Do not touch the database while the first-run installer is still open.
    if (!function_exists('is_installed') || !is_installed()) return;
    try {
        $cfg = app_config()['db'] ?? [];
        if (empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) return;
        $pdo=db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS navigation_menus (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, location VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, tree_json LONGTEXT NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL, INDEX(updated_by), INDEX(updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_posts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,title VARCHAR(220) NOT NULL,slug VARCHAR(220) NOT NULL UNIQUE,content LONGTEXT NOT NULL,status ENUM('draft','published') NOT NULL DEFAULT 'draft',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(status),INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,slug VARCHAR(160) NOT NULL UNIQUE,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done=true;
    } catch (Throwable $e) {
        // Navigation schema failures must not break the installer or the site shell.
    }
}
function menu_default_tree(string $location='header'): array {
    $items=[];
    $defs= $location==='footer' ? [
        ['product','Product','/product/','page',null],['features','Features','/features/','page',null],['pricing','Pricing','/pricing/','page',null],['faq','FAQ','/faq/','page',null],['security','Security','/security/','page',null],['privacy','Privacy','/privacy/','page',null],['terms','Terms','/terms/','page',null],['contact','Contact','/contact/','page',null]
    ] : ($location==='sidebar' ? [
        ['home','Home','/','page',null],['tasks','Tasks','/tasks/','page',null],['notes','Notes','/notes/','page',null],['projects','Projects','/projects/','page',null],['search','Search','/search/','page',null]
    ] : [
        ['home','Home','/','page',null],['product','Product','/product/','page',null],['features','Features','/features/','page',null],
        ['how-it-works','How it works','/how-it-works/','page',null],['pricing','Pricing','/pricing/','page',null],['about','About','/about/','page',null],['contact','Contact','/contact/','page',null]
    ]);
    foreach($defs as $i=>$d)$items[]=['id'=>'seed_'.$d[0],'label'=>$d[1],'url'=>$d[2],'type'=>$d[3],'object_id'=>$d[4],'order_index'=>$i,'target'=>'','nofollow'=>false,'title'=>'','css_class'=>'','children'=>[]];
    return $items;
}
function menu_locations(): array { return ['header'=>'Header Navigation','footer'=>'Footer Navigation','sidebar'=>'Sidebar Navigation']; }
function menu_get(string $location='header'): array {
    menu_schema(); $location=array_key_exists($location,menu_locations())?$location:'header';
    $s=db()->prepare('SELECT * FROM navigation_menus WHERE location=? LIMIT 1');$s->execute([$location]);$r=$s->fetch();
    if(!$r){$tree=menu_default_tree($location); db()->prepare('INSERT INTO navigation_menus(location,name,tree_json,updated_at) VALUES(?,?,?,NOW())')->execute([$location,menu_locations()[$location],json_encode($tree,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]); return ['location'=>$location,'name'=>menu_locations()[$location],'version'=>1,'tree'=>$tree];}
    $tree=json_decode((string)$r['tree_json'],true); if(!is_array($tree))$tree=[];
    return ['location'=>$location,'name'=>$r['name'],'version'=>(int)$r['version'],'tree'=>menu_normalize_tree($tree)];
}
function menu_normalize_tree(array $tree): array {
    $out=[];$order=0;
    foreach($tree as $item){ if(!is_array($item))continue; $id=preg_replace('/[^A-Za-z0-9_-]/','',(string)($item['id']??'')); if($id==='')$id='mi_'.bin2hex(random_bytes(5));
        $url=(string)($item['url']??'#'); if(strlen($url)>2000)$url=substr($url,0,2000);
        $type=in_array(($item['type']??'custom'),['page','post','category','custom'],true)?$item['type']:'custom';
        $out[]=['id'=>$id,'label'=>trim(substr(strip_tags((string)($item['label']??'')),0,180)),'url'=>$url,'type'=>$type,'object_id'=>isset($item['object_id'])&&is_numeric($item['object_id'])?(int)$item['object_id']:null,'order_index'=>$order++,'target'=>($item['target']??'')==='_blank'?'_blank':'','nofollow'=>!empty($item['nofollow']),'title'=>trim(substr(strip_tags((string)($item['title']??'')),0,180)),'css_class'=>preg_replace('/[^A-Za-z0-9_ -]/','',(string)($item['css_class']??'')),'children'=>menu_normalize_tree(is_array($item['children']??null)?$item['children']:[])];
    }
    return $out;
}
function menu_validate_tree(array $tree, array &$seen=[]): array {
    if(count($tree)>200)throw new RuntimeException('A menu cannot contain more than 200 items.');
    $clean=menu_normalize_tree($tree);
    $walk=function($nodes,$depth)use(&$walk,&$seen){ if($depth>8)throw new RuntimeException('Maximum menu depth is 8 levels.'); foreach($nodes as $n){if(isset($seen[$n['id']]))throw new RuntimeException('Duplicate menu item id.');$seen[$n['id']]=1;$walk($n['children'],$depth+1);} };
    $walk($clean,0); return $clean;
}
function menu_save(string $location,array $tree,int $expectedVersion): array {
    menu_schema(); $locations=menu_locations(); if(!isset($locations[$location]))throw new RuntimeException('Invalid menu location.');
    $seen=[];$clean=menu_validate_tree($tree,$seen);$pdo=db();$pdo->beginTransaction();
    try{$s=$pdo->prepare('SELECT version FROM navigation_menus WHERE location=? FOR UPDATE');$s->execute([$location]);$v=$s->fetchColumn();if($v===false){$pdo->prepare('INSERT INTO navigation_menus(location,name,tree_json,version,updated_by,updated_at) VALUES(?,?,?,1,?,NOW())')->execute([$location,$locations[$location],json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)current_user()['id']]);$new=1;}else{if((int)$v!==$expectedVersion)throw new RuntimeException('Menu changed elsewhere. Reload before saving.');$new=(int)$v+1;$pdo->prepare('UPDATE navigation_menus SET tree_json=?,version=?,updated_by=?,updated_at=NOW() WHERE location=?')->execute([json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$new,(int)current_user()['id'],$location]);}$pdo->commit();return ['location'=>$location,'version'=>$new,'tree'=>$clean];}catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function menu_source_items(string $kind,string $q=''): array {
    $q=trim($q); $like='%'.$q.'%'; $pdo=db(); $out=[];
    if($kind==='pages'){
        $st=$pdo->prepare("SELECT page_slug,title FROM site_content WHERE title LIKE ? OR page_slug LIKE ? ORDER BY title LIMIT 100");$st->execute([$like,$like]);
        foreach($st->fetchAll() as $r)$out[]=['object_id'=>null,'label'=>$r['title']?:$r['page_slug'],'url'=>'/'.trim($r['page_slug'],'/').($r['page_slug']==='home'?'':'/'),'type'=>'page'];
        foreach(menu_default_tree('header') as $d) if(!$q || stripos($d['label'],$q)!==false)$out[]=['object_id'=>null,'label'=>$d['label'],'url'=>$d['url'],'type'=>'page'];
    } elseif($kind==='posts') {
        try{$cols=$pdo->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $e){$cols=[];}
        if($cols && in_array('title',$cols,true)){ $status=in_array('status',$cols,true)?" AND status='published'":''; $st=$pdo->prepare("SELECT id oid,title label,slug FROM posts WHERE title LIKE ? $status ORDER BY title LIMIT 100");$st->execute([$like]);foreach($st->fetchAll() as $r)$out[]=['object_id'=>(int)$r['oid'],'label'=>$r['label'],'url'=>'/posts/?slug='.rawurlencode((string)$r['slug']),'type'=>'post']; }
        else { $st=$pdo->prepare("SELECT id,title,slug FROM cms_posts WHERE status='published' AND title LIKE ? ORDER BY title LIMIT 100");$st->execute([$like]);foreach($st->fetchAll() as $r)$out[]=['object_id'=>(int)$r['id'],'label'=>$r['title'],'url'=>'/posts/?slug='.rawurlencode((string)$r['slug']),'type'=>'post']; }
    } elseif($kind==='categories') {
        $st=$pdo->prepare('SELECT id,name,slug FROM cms_categories WHERE name LIKE ? ORDER BY name LIMIT 100');$st->execute([$like]);foreach($st->fetchAll() as $r)$out[]=['object_id'=>(int)$r['id'],'label'=>$r['name'],'url'=>'/category/?slug='.rawurlencode((string)$r['slug']),'type'=>'category'];
    }
    return $out;
}
function menu_render(string $location='header',string $class='pc-menu'): string {
    $tree=menu_get($location)['tree']; $render=function($nodes)use(&$render,$class){$html='<ul class="'.$class.'-list">'; foreach($nodes as $n){$attrs=' href="'.e($n['url']?:'#').'"';if($n['target']==='_blank')$attrs.=' target="_blank" rel="noopener'.($n['nofollow']?' nofollow':''). '"';elseif($n['nofollow'])$attrs.=' rel="nofollow"';if($n['title'])$attrs.=' title="'.e($n['title']).'"';$html.='<li class="'.$class.'-item"><a'.$attrs.' class="'.e($n['css_class']).'">'.e($n['label']).'</a>';if($n['children'])$html.=$render($n['children']);$html.='</li>';}$html.='</ul>';return $html;}; return $render($tree);
}
