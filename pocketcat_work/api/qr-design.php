<?php
require __DIR__.'/../bootstrap.php';
require_login();
if(!function_exists('qr_schema'))qr_schema();
$u=current_user();$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');$linkId=(int)($_GET['link_id']??$_POST['link_id']??0);
if(!$linkId)json_response(['ok'=>false,'message'=>'link_id is required.'],400);
$q=db()->prepare('SELECT id,slug,user_id FROM short_links WHERE id=? LIMIT 1');$q->execute([$linkId]);$link=$q->fetch();
if(!$link||!user_can_link((int)$u['id'],(int)$link['user_id']))json_response(['ok'=>false,'message'=>'Link not found.'],404);
if($method==='GET'){json_response(['ok'=>true,'data'=>['link_id'=>$linkId,'slug'=>$link['slug'],'url'=>link_public_url($link['slug']),'design'=>qr_design_get($linkId)]]);}
if(!in_array($method,['POST','PUT'],true))json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
verify_csrf();
$design=[];
if(isset($_POST['design'])){$design=json_decode((string)$_POST['design'],true);if(!is_array($design))$design=[];}
else{$raw=json_decode((string)file_get_contents('php://input'),true);if(is_array($raw))$design=$raw;}
foreach(['background_image','foreground_image'] as $field){if(!empty($_FILES[$field]))$design[$field]=qr_upload_image($field,(int)$u['id']);}
qr_design_save($linkId,$design);json_response(['ok'=>true,'data'=>['design'=>qr_design_get($linkId)]]);
