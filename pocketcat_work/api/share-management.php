<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
ensure_file_workspace_schema();
header('Content-Type: application/json; charset=utf-8');
$uid=(int)current_user()['id']; $db=db();
function share_mgmt_json(array $data,int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
try {
  $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
  if($method==='GET') {
    $status=(string)($_GET['status']??'all'); if(!in_array($status,['all','active','expired','revoked'],true))$status='all';
    $sql="SELECT s.id,s.title,s.token,s.link_id,s.created_at,s.expires_at,s.revoked_at,s.downloads,s.max_downloads,s.view_only,COUNT(DISTINCT i.file_id) item_count,MAX(e.occurred_at) last_access FROM file_share_links s LEFT JOIN file_share_items i ON i.share_id=s.id LEFT JOIN file_share_events e ON e.share_id=s.id WHERE s.user_id=?";
    $p=[$uid];
    if($status==='active')$sql.=" AND s.revoked_at IS NULL AND (s.expires_at IS NULL OR s.expires_at>NOW()) AND (s.max_downloads=0 OR s.downloads<s.max_downloads)";
    elseif($status==='expired')$sql.=" AND s.revoked_at IS NULL AND ((s.expires_at IS NOT NULL AND s.expires_at<=NOW()) OR (s.max_downloads>0 AND s.downloads>=s.max_downloads))";
    elseif($status==='revoked')$sql.=" AND s.revoked_at IS NOT NULL";
    $sql.=" GROUP BY s.id ORDER BY s.created_at DESC LIMIT 200";
    $q=$db->prepare($sql);$q->execute($p);$items=$q->fetchAll();
    foreach($items as &$item){$item['status']=!empty($item['revoked_at'])?'revoked':((!empty($item['expires_at'])&&strtotime($item['expires_at'])<=time())||((int)$item['max_downloads']>0&&(int)$item['downloads']>=(int)$item['max_downloads'])?'expired':'active');$item['url']=file_share_url((string)$item['token']);unset($item['token']);}unset($item);
    share_mgmt_json(['ok'=>true,'items'=>$items]);
  }
  if($method!=='POST')share_mgmt_json(['ok'=>false,'error'=>'Method not allowed'],405);
  verify_csrf();$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=$_POST;
  $action=(string)($body['action']??'');$id=(int)($body['id']??0);if($id<1)share_mgmt_json(['ok'=>false,'error'=>'A valid share ID is required.'],422);
  if($action==='revoke'){
    $db->beginTransaction();$q=$db->prepare('SELECT id,revoked_at FROM file_share_links WHERE id=? AND user_id=? FOR UPDATE');$q->execute([$id,$uid]);$row=$q->fetch();if(!$row){$db->rollBack();share_mgmt_json(['ok'=>false,'error'=>'Share not found.'],404);}if(empty($row['revoked_at']))$db->prepare('UPDATE file_share_links SET revoked_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=? AND revoked_at IS NULL')->execute([$id,$uid]);
    $db->prepare("INSERT INTO storage_audit_events(user_id,actor_id,action,resource_type,resource_id,metadata_json,created_at) VALUES(?,?,'SHARE_REVOKE','share',?,?,NOW())")->execute([$uid,$uid,$id,json_encode(['result'=>'revoked'])]);$db->commit();share_mgmt_json(['ok'=>true,'revoked'=>true]);
  }
  if($action==='regenerate'){
    $db->beginTransaction();$q=$db->prepare('SELECT * FROM file_share_links WHERE id=? AND user_id=? FOR UPDATE');$q->execute([$id,$uid]);$row=$q->fetch();if(!$row){$db->rollBack();share_mgmt_json(['ok'=>false,'error'=>'Share not found.'],404);}if(!empty($row['expires_at'])&&strtotime((string)$row['expires_at'])<=time()){$db->rollBack();share_mgmt_json(['ok'=>false,'error'=>'This link is expired. Create a new share with a new expiration instead.'],409);} $token=bin2hex(random_bytes(24));$db->prepare('UPDATE file_share_links SET token=?,revoked_at=NULL,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$token,$id,$uid]);if(!empty($row['link_id'])){$slug=link_slug();$dest=file_share_url($token);$db->prepare('UPDATE short_links SET slug=?,destination_url=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$slug,$dest,(int)$row['link_id'],$uid]);}$db->prepare("INSERT INTO storage_audit_events(user_id,actor_id,action,resource_type,resource_id,metadata_json,created_at) VALUES(?,?,'SHARE_REGENERATE','share',?,?,NOW())")->execute([$uid,$uid,$id,json_encode(['result'=>'regenerated'])]);$db->commit();$url=!empty($row['link_id'])?link_public_url($slug):file_share_url($token);share_mgmt_json(['ok'=>true,'url'=>$url]);
  }
  share_mgmt_json(['ok'=>false,'error'=>'Unsupported share action.'],422);
} catch(Throwable $e) { if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('[PocketCat share management] '.$e->getMessage());share_mgmt_json(['ok'=>false,'error'=>'Share management failed safely.'],500); }
