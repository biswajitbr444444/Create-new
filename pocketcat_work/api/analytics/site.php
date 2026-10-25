<?php
require __DIR__.'/../../bootstrap.php';require_login();verify_csrf();
try{
    $u=current_user();analytics_require_premium($u);$id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??'My Website'));$domain=trim((string)($_POST['domain']??''));
    if($name==='')throw new InvalidArgumentException('Website name is required.');
    if($id){$site=analytics_get_site($id);if(!$site||(($u['role']??'')!=='admin'&&(int)$site['owner_user_id']!==(int)$u['id']))throw new RuntimeException('Website not found.');db()->prepare('UPDATE analytics_sites SET name=?,domain=?,enabled=?,consent_required=?,anonymize_ip=?,retention_days=?,updated_at=NOW() WHERE id=?')->execute([$name,$domain,(int)isset($_POST['enabled']),(int)isset($_POST['consent_required']),(int)isset($_POST['anonymize_ip']),max(30,min(3650,(int)($_POST['retention_days']??365))),$id]);$site=analytics_get_site($id);}else{$site=analytics_create_site((int)$u['id'],$name,$domain);}
    json_response(['success'=>true,'data'=>$site,'snippet'=>analytics_tracking_snippet($site)]);
}catch(Throwable $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}
