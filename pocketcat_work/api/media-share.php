<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
ensure_file_workspace_schema();
header('Content-Type: application/json; charset=utf-8');
function media_share_json(array $v,int $status=200): never { http_response_code($status); echo json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') media_share_json(['ok'=>false,'error'=>'POST required'],405);
    verify_csrf();
    $uid=(int)current_user()['id'];
    $input=json_decode((string)file_get_contents('php://input'),true); if(!is_array($input))$input=[];
    $ids=$input['ids']??$_POST['ids']??[]; if(!is_array($ids))$ids=[];
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));
    $folders=$input['folders']??$_POST['folders']??[];if(!is_array($folders))$folders=[];$folders=array_values(array_unique(array_filter(array_map(fn($v)=>trim((string)$v),$folders),fn($v)=>$v!==''&&strlen($v)<=180)));
    if((!$ids&&!$folders)||count($ids)>100||count($folders)>50)media_share_json(['ok'=>false,'error'=>'Select files or folders (up to 100 files and 50 folders).'],422);
    $mode=(string)($input['mode']??$_POST['mode']??'expiry');
    $preset=(string)($input['expires']??$_POST['expires']??'7d');
    $durations=['1h'=>3600,'6h'=>21600,'24h'=>86400,'2d'=>172800,'7d'=>604800,'1m'=>2592000,'never'=>0];
    if(!isset($durations[$preset]))media_share_json(['ok'=>false,'error'=>'Invalid expiration preset.'],422);
    $pdo=db(); $ph=implode(',',array_fill(0,count($ids),'?'));
    $rows=[];$seen=[];
    if($ids){$q=$pdo->prepare("SELECT m.id,m.name,m.folder_name,m.canonical_file_id,f.id AS fid,f.status,f.scan_status FROM media_assets m LEFT JOIN file_objects f ON f.id=m.canonical_file_id AND f.user_id=m.user_id WHERE m.user_id=? AND m.name<>'.folder-marker' AND m.id IN ($ph)");$q->execute(array_merge([$uid],$ids));$rows=$q->fetchAll();if(count($rows)!==count($ids))media_share_json(['ok'=>false,'error'=>'One or more selected items are unavailable.'],404);foreach($rows as $r)$seen[(int)$r['id']]=true;}
    if($folders){$q=$pdo->prepare("SELECT m.id,m.name,m.folder_name,m.canonical_file_id,f.id AS fid,f.status,f.scan_status FROM media_assets m LEFT JOIN file_objects f ON f.id=m.canonical_file_id AND f.user_id=m.user_id WHERE m.user_id=? AND m.name<>'.folder-marker'");$q->execute([$uid]);foreach($q->fetchAll() as $r){foreach($folders as $folder){if($r['folder_name']===$folder||str_starts_with((string)$r['folder_name'],rtrim($folder,'/').'/')){if(!isset($seen[(int)$r['id']])){$rows[]=$r;$seen[(int)$r['id']]=true;}break;}}}}
    if(!$rows)media_share_json(['ok'=>false,'error'=>'The selected folders contain no shareable files.'],422);
    if(count($rows)>1000)media_share_json(['ok'=>false,'error'=>'A single share can contain at most 1,000 files.'],422);
    $fileIds=[];
    foreach($rows as $r){ if(empty($r['fid']))media_share_json(['ok'=>false,'error'=>'Secure sharing is available only for files registered in File Storage. Please re-upload or import the selected remote URL into File Storage first.'],422); if(($r['status']??'')!=='clean'||!in_array(($r['scan_status']??''),['clean','skipped'],true))media_share_json(['ok'=>false,'error'=>'One or more selected files are not cleared for sharing.'],422); $fileIds[]=(int)$r['fid']; }
    $fileIds=array_values(array_unique($fileIds));
    $expiresAt=$durations[$preset]>0?date('Y-m-d H:i:s',time()+$durations[$preset]):null;
    $title=count($rows)===1?(string)$rows[0]['name']:(count($rows).' shared items');
    $token=bin2hex(random_bytes(24));
    $pdo->beginTransaction();
    $ins=$pdo->prepare('INSERT INTO file_share_links(user_id,token,title,expires_at,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())');
    $ins->execute([$uid,$token,mb_substr($title,0,220),$expiresAt]); $shareId=(int)$pdo->lastInsertId();
    $itemIns=$pdo->prepare('INSERT INTO file_share_items(share_id,file_id,sort_order) VALUES(?,?,?)');
    foreach($fileIds as $i=>$fid)$itemIns->execute([$shareId,$fid,$i]);
    $shareUrl=file_share_url($token); $shortUrl=$shareUrl; $shortId=null;
    if($mode==='analytics'){
      $slug=link_slug();
      $li=$pdo->prepare('INSERT INTO short_links(user_id,slug,destination_url,title,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())');
      $li->execute([$uid,$slug,$shareUrl,mb_substr($title,0,220),$expiresAt]); $shortId=(int)$pdo->lastInsertId();
      $pdo->prepare('UPDATE file_share_links SET link_id=? WHERE id=? AND user_id=?')->execute([$shortId,$shareId,$uid]);
      $shortUrl=link_public_url($slug);
    }
    $pdo->commit();
    media_share_json(['ok'=>true,'url'=>$shortUrl,'share_url'=>$shareUrl,'expires_at'=>$expiresAt,'count'=>count($fileIds),'link_id'=>$shortId,'redirect'=>$mode==='analytics'?'/links/?created='.$shortId.'#myQrCode':null]);
} catch(Throwable $e) { if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack(); error_log('[PocketCat media share] '.$e->getMessage()); media_share_json(['ok'=>false,'error'=>'Could not create a secure share link. Please try again.'],500); }
