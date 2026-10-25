<?php
require __DIR__.'/../bootstrap.php';
require_login();
header('Cache-Control: no-store');

$u=current_user();
if(!hash_equals(csrf_token(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))json_response(['error'=>'csrf'],419);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='PATCH')json_response(['error'=>'method_not_allowed'],405);

$id=(int)($_GET['id']??0);
if($id<1)json_response(['error'=>'invalid_id'],422);
$q=db()->prepare('SELECT * FROM short_links WHERE id=? AND user_id=? LIMIT 1');
$q->execute([$id,(int)$u['id']]);
$link=$q->fetch();
if(!$link)json_response(['error'=>'not_found'],404);

$body=json_decode(file_get_contents('php://input'),true);
if(!is_array($body))$body=[];
try{
    $dest=normalize_destination((string)($body['destination_url']??''));
    $th=threat_check_url($dest);
    if(!$th['ok'])json_response(['error'=>'unsafe_destination','message'=>$th['message']],422);
    $title=trim((string)($body['title']??''));
    $expires=null;
    if(!empty($body['expires_at'])){
        $t=strtotime((string)$body['expires_at']);
        if(!$t)throw new InvalidArgumentException('Invalid expiration date.');
        $expires=date('Y-m-d H:i:s',$t);
    }
    $maxClicks=max(0,(int)($body['max_clicks']??0));
    $status=($body['status']??'active')==='disabled'?'disabled':'active';
    db()->prepare('UPDATE short_links SET destination_url=?,title=?,expires_at=?,max_clicks=?,status=?,updated_at=NOW() WHERE id=? AND user_id=?')
      ->execute([$dest,$title,$expires,$maxClicks,$status,$id,(int)$u['id']]);
    json_response(['data'=>[
        'id'=>$id,
        'slug'=>$link['slug'],
        'short_url'=>link_public_url($link['slug']),
        'destination_url'=>$dest,
        'title'=>$title,
        'expires_at'=>$expires,
        'max_clicks'=>$maxClicks,
        'status'=>$status,
        'updated'=>true
    ]]);
}catch(InvalidArgumentException $e){json_response(['error'=>'validation','message'=>$e->getMessage()],422);}catch(Throwable $e){json_response(['error'=>'server_error'],500);}
