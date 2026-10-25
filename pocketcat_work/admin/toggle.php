<?php
require __DIR__.'/../bootstrap.php'; require_login();
if((current_user()['role']??'')!=='admin'){http_response_code(403);exit('403 Forbidden');}
$_SESSION['admin_view']=!empty($_SESSION['admin_view'])?false:true;
redirect(is_admin_view()?'/admin/':'/dashboard/');
