<?php
require __DIR__.'/../bootstrap.php'; require_admin(); ensure_cms_schema();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
verify_csrf();
$kind=(string)post('kind',''); $name=trim((string)post('name','')); $slug=trim((string)post('slug',$name));
if(!in_array($kind,['category','tag'],true) || $name===''){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Name is required.']);exit;}
$slug=cms_slug($slug);
try{
 $table=$kind==='category'?'cms_categories':'cms_tags';
 $q=db()->prepare("SELECT id FROM {$table} WHERE slug=? LIMIT 1");$q->execute([$slug]);
 if($q->fetchColumn()){ $slug.='-'.substr(bin2hex(random_bytes(3)),0,6); }
 if($kind==='category'){
  $s=db()->prepare('INSERT INTO cms_categories(name,slug,description,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())');$s->execute([$name,$slug,'']);
 }else{
  $s=db()->prepare('INSERT INTO cms_tags(name,slug,description,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())');$s->execute([$name,$slug,'']);
 }
 header('Content-Type: application/json');echo json_encode(['ok'=>true,'id'=>(int)db()->lastInsertId(),'name'=>$name,'slug'=>$slug]);
}catch(Throwable $e){http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Could not create taxonomy term.']);}
