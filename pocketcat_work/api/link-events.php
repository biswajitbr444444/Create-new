<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';require_login();
header('Cache-Control: no-store');header('Content-Type: application/json; charset=utf-8');
$u=current_user();$linkId=(int)($_GET['id']??0);$includeBots=($_GET['include_bots']??'0')==='1';$since=(int)($_GET['since']??0);
$q=db()->prepare('SELECT * FROM short_links WHERE id=? AND user_id=?');$q->execute([$linkId,(int)$u['id']]);if(!$q->fetch()){http_response_code(404);echo json_encode(['error'=>'Link not found']);exit;}
$where='link_id=?';$params=[$linkId];if(!$includeBots)$where.=' AND is_bot=0';if($since>0){$where.=' AND id>?';$params[]=$since;}
$s=db()->prepare("SELECT id,ip_address,clicked_at,clicked_at_utc,timezone_name,referrer,browser,browser_version,browser_engine,os,os_version,device_category,device_brand,device_model,country_code,country_name,region,city,postal_code,latitude,longitude,isp,asn,accept_language,utm_source,utm_medium,utm_campaign,utm_term,utm_content,is_bot,bot_type,user_agent FROM link_clicks WHERE $where ORDER BY id DESC LIMIT 100");$s->execute($params);$rows=$s->fetchAll();
$events=array_map(function($r){$r['id']=(int)$r['id'];$r['is_bot']=(int)$r['is_bot'];$r['latitude']=$r['latitude']!==null?(float)$r['latitude']:null;$r['longitude']=$r['longitude']!==null?(float)$r['longitude']:null;return $r;},$rows);
$latestId=$events?max(array_column($events,'id')):$since;
echo json_encode(['data'=>$events,'latest_id'=>$latestId,'server_time'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
