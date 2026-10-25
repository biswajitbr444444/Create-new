<?php
require __DIR__.'/../bootstrap.php';
if(empty($_SESSION['impersonator_id']))redirect('/login/');
$adminId=(int)$_SESSION['impersonator_id'];policy_stop_impersonation();$_SESSION['user_id']=$adminId;$_SESSION['admin_view']=true;redirect('/admin/control-plane/');
