<?php
require __DIR__.'/../../../bootstrap.php';
require_admin();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['ok'=>false,'error'=>'POST required'],405);
verify_csrf();
$raw=file_get_contents('php://input');$data=json_decode($raw,true);
if(!is_array($data))$data=$_POST;
try{
  $location=(string)($data['location']??'header'); $tree=$data['tree']??[]; $version=(int)($data['version']??0);
  if(!is_array($tree))throw new RuntimeException('Invalid menu tree.');
  $result=menu_save($location,$tree,$version);
  if(function_exists('log_event'))log_event('navigation_menu_saved',(int)current_user()['id']);
  json_response(['ok'=>true]+$result);
}catch(Throwable $e){
  $msg=$e instanceof RuntimeException?$e->getMessage():'Unable to save menu.';
  $status=str_contains($msg,'changed elsewhere')?409:422; json_response(['ok'=>false,'error'=>$msg],$status);
}
