<?php
require __DIR__.'/../../bootstrap.php';require_login();
try{
    $u=current_user();analytics_require_premium($u);$siteId=(int)($_GET['site_id']??0);$site=$siteId?analytics_get_site($siteId):analytics_owned_site((int)$u['id']);
    if(!$site)json_response(['success'=>false,'message'=>'No analytics site configured.'],404);
    if(($u['role']??'')!=='admin' && (int)$site['owner_user_id']!==(int)$u['id'])json_response(['success'=>false,'message'=>'Forbidden.'],403);
    json_response(['success'=>true,'data'=>analytics_report((int)$site['id'],(string)($_GET['range']??'7d'))]);
}catch(Throwable $e){json_response(['success'=>false,'message'=>$e->getMessage()],500);}
