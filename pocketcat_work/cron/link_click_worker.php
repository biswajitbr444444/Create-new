<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

if(PHP_SAPI!=='cli' && !isset($_GET['token'])){http_response_code(403);exit('CLI only.');}
$limit=max(1,min(200,(int)($_GET['limit']??($argv[1]??50))));
$pdo=db();$done=0;
for($i=0;$i<$limit;$i++){
    $job=null;$pdo->beginTransaction();
    try{
        $q=$pdo->query("SELECT * FROM link_click_queue WHERE status='pending' AND available_at<=NOW() ORDER BY id ASC LIMIT 1");
        $job=$q->fetch();
        if(!$job){$pdo->commit();break;}
        $claimed=$pdo->prepare("UPDATE link_click_queue SET status='processing',locked_at=NOW(),attempts=attempts+1,updated_at=NOW() WHERE id=? AND status='pending'");
        $claimed->execute([(int)$job['id']]);
        if($claimed->rowCount()!==1){$pdo->rollBack();continue;}
        $pdo->commit();
        $payload=json_decode((string)$job['payload_json'],true);
        if(!is_array($payload))throw new RuntimeException('Invalid queue payload');
        $linkId=(int)$payload['link_id'];
        $geo=['country_code'=>(string)($payload['country_code']??''),'country_name'=>(string)($payload['country_name']??''),'region'=>(string)($payload['region']??''),'city'=>(string)($payload['city']??''),'postal_code'=>(string)($payload['postal_code']??''),'latitude'=>$payload['latitude']??null,'longitude'=>$payload['longitude']??null,'timezone'=>(string)($payload['timezone_name']??''),'isp'=>(string)($payload['isp']??''),'asn'=>(string)($payload['asn']??'')];if(!$geo['country_code']&&!empty($payload['ip']))$geo=geo_lookup((string)$payload['ip'])?:$geo;
        $insert=$pdo->prepare('INSERT INTO link_clicks(link_id,visitor_hash,ip_hash,ip_address,referrer,browser,browser_version,browser_engine,os,os_version,device_category,device_brand,device_model,country_code,country_name,region,city,postal_code,latitude,longitude,timezone_name,isp,asn,accept_language,utm_source,utm_medium,utm_campaign,utm_term,utm_content,is_bot,bot_type,user_agent,clicked_at,clicked_at_utc) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([
            $linkId,(string)($payload['visitor_hash']??''),(string)($payload['ip_hash']??''),(string)($payload['ip']??''),(string)($payload['referrer']??''),
            (string)($payload['browser']??'Other'),(string)($payload['browser_version']??''),(string)($payload['browser_engine']??''),
            (string)($payload['os']??'Other'),(string)($payload['os_version']??''),(string)($payload['device_category']??'Desktop'),
            (string)($payload['device_brand']??''),(string)($payload['device_model']??''),
            (string)($geo['country_code']??$payload['country_code']??''),(string)($geo['country_name']??''),(string)($geo['region']??''),(string)($geo['city']??$payload['city']??''),(string)($geo['postal_code']??''),
            $geo['latitude']??null,$geo['longitude']??null,(string)($geo['timezone']??$payload['timezone_name']??''),(string)($geo['isp']??''),(string)($geo['asn']??''),
            (string)($payload['accept_language']??''),(string)($payload['utm_source']??''),(string)($payload['utm_medium']??''),(string)($payload['utm_campaign']??''),(string)($payload['utm_term']??''),(string)($payload['utm_content']??''),
            (int)($payload['is_bot']??0),(string)($payload['bot_type']??''),(string)($payload['user_agent']??''),
            (string)($payload['clicked_at']??gmdate('Y-m-d H:i:s')),(string)($payload['clicked_at_utc']??gmdate('Y-m-d H:i:s'))
        ]);
        if(empty($payload['is_bot'])){
            $pdo->prepare('UPDATE short_links SET clicks=clicks+1,last_clicked_at=NOW() WHERE id=?')->execute([$linkId]);
            $linkQ=$pdo->prepare('SELECT * FROM short_links WHERE id=?');$linkQ->execute([$linkId]);$link=$linkQ->fetch();
            if($link){
                $newClicks=(int)$link['clicks'];
                if($newClicks===1000)link_webhook_event($linkId,['event'=>'milestone','milestone'=>1000,'link_id'=>$linkId,'slug'=>$link['slug'],'url'=>link_public_url($link['slug'])]);
                link_webhook_event($linkId,['event'=>'click','link_id'=>$linkId,'slug'=>$link['slug'],'destination'=>(string)($payload['destination']??$link['destination_url']),'timestamp'=>gmdate('c'),'country'=>(string)($geo['country_code']??$payload['country_code']??''),'city'=>(string)($geo['city']??$payload['city']??''),'os'=>(string)($payload['os']??''),'device'=>(string)($payload['device_category']??'')]);
            }
        }
        $pdo->prepare("UPDATE link_click_queue SET status='done',processed_at=NOW(),updated_at=NOW(),last_error=NULL WHERE id=?")->execute([(int)$job['id']]);
        $done++;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        try{
            if(!$job)continue;
            $attempts=(int)($job['attempts']??0)+1;$status=$attempts>=5?'failed':'pending';
            $delay=min(3600,60*$attempts);
            $pdo->prepare("UPDATE link_click_queue SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=?,updated_at=NOW() WHERE id=?")->execute([$status,$delay,substr($e->getMessage(),0,500),(int)$job['id']]);
        }catch(Throwable $ignored){}
    }
}
if(PHP_SAPI==='cli')echo "processed={$done}\n";
