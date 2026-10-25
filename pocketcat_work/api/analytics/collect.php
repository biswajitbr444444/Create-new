<?php
require __DIR__.'/../../bootstrap.php';
header('Cache-Control: no-store');
try{
    $raw=file_get_contents('php://input');$data=json_decode($raw?:'',true);if(!is_array($data))$data=$_POST;
    $site=analytics_get_site_by_key((string)($data['site_key']??''));if(!$site)json_response(['success'=>false,'message'=>'Invalid analytics site.'],404);
    $limit=(int)($site['retention_days']??365);$ipHash=analytics_hash(client_ip().'|'.$site['id']);$q=db()->prepare("SELECT COUNT(*) FROM analytics_events WHERE site_id=? AND ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)");$q->execute([(int)$site['id'],$ipHash]);if((int)$q->fetchColumn()>120)json_response(['success'=>false,'message'=>'Rate limit exceeded.'],429);
    json_response(analytics_collect($data,$site));
}catch(Throwable $e){error_log('[PocketCat analytics collect] '.$e->getMessage());json_response(['success'=>false,'message'=>'Analytics event could not be recorded.'],500);}
