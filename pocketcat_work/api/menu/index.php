<?php
require __DIR__.'/../../bootstrap.php';
require_admin();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST'){
    verify_csrf(); $d=json_decode(file_get_contents('php://input'),true); if(!is_array($d))$d=$_POST; $action=(string)($d['action']??'');
    try{
        if($action==='create_page'){
            $label=trim((string)($d['title']??'')); if($label==='')throw new RuntimeException('Page title is required.');
            $slug=trim((string)($d['slug']??'')); if($slug==='')$slug=preg_replace('/[^a-z0-9]+/i','-',strtolower($label)); $slug=trim($slug,'-'); if($slug==='')throw new RuntimeException('A valid page slug is required.');
            $base=$slug;$i=2;$st=db()->prepare('SELECT COUNT(*) FROM site_content WHERE page_slug=?');$st->execute([$slug]);while((int)$st->fetchColumn()>0){$slug=$base.'-'.$i++;$st->execute([$slug]);}
            $html='<h1>'.e($label).'</h1><p>Start writing this page.</p>';db()->prepare('INSERT INTO site_content(page_slug,title,html_content,updated_at) VALUES(?,?,?,NOW())')->execute([$slug,$label,$html]);
            json_response(['ok'=>true,'item'=>['object_id'=>null,'label'=>$label,'url'=>'/'.$slug.'/','type'=>'page']]);
        }
        if($action==='create_post'){
            $label=trim((string)($d['title']??''));if($label==='')throw new RuntimeException('Post title is required.');
            $pdo=db();try{$cols=$pdo->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $e){$cols=[];}
            if(!$cols || !in_array('title',$cols,true)){
                $slug=trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($label)),'-'); $base=$slug;$i=2;$st=$pdo->prepare('SELECT COUNT(*) FROM cms_posts WHERE slug=?');$st->execute([$slug]);while((int)$st->fetchColumn()>0){$slug=$base.'-'.$i++;$st->execute([$slug]);}
                $pdo->prepare('INSERT INTO cms_posts(user_id,title,slug,content,status,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())')->execute([(int)current_user()['id'],$label,$slug,'<h1>'.e($label).'</h1><p>Draft post.</p>','draft']); $id=(int)$pdo->lastInsertId(); json_response(['ok'=>true,'item'=>['object_id'=>$id,'label'=>$label,'url'=>'/posts/?slug='.rawurlencode($slug),'type'=>'post']]);
            }
            $fields=['title'];$vals=[$label];foreach(['status','slug','content'] as $c)if(in_array($c,$cols,true)){$fields[]=$c;$vals[]=$c==='status'?'draft':($c==='slug'?trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($label)),'-'):'');}
            if(in_array('user_id',$cols,true)){$fields[]='user_id';$vals[]=(int)current_user()['id'];}foreach(['created_at','updated_at'] as $c)if(in_array($c,$cols,true)){$fields[]=$c;$vals[]=date('Y-m-d H:i:s');}
            $sql='INSERT INTO posts('.implode(',',array_map(fn($x)=>'`'.$x.'`',$fields)).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')';$pdo->prepare($sql)->execute($vals);$id=(int)$pdo->lastInsertId();$slug=trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($label)),'-');
            json_response(['ok'=>true,'item'=>['object_id'=>$id,'label'=>$label,'url'=>'/posts/?slug='.rawurlencode($slug),'type'=>'post']]);
        }
        throw new RuntimeException('Unknown action.');
    }catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],422);}
}
if($method!=='GET')json_response(['ok'=>false,'error'=>'GET or POST required'],405);
$kind=(string)getv('source','');$q=(string)getv('q','');
if($kind && in_array($kind,['pages','posts','categories'],true))json_response(['ok'=>true,'items'=>menu_source_items($kind,$q)]);
$location=(string)getv('location','header');json_response(['ok'=>true,'menu'=>menu_get($location),'locations'=>menu_locations()]);
