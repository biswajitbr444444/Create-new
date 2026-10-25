<?php
require __DIR__.'/../bootstrap.php';require_admin();
$c=app_config()['db'];$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $n=['host'=>trim((string)post('host')),'port'=>trim((string)post('port','3306')),'name'=>trim((string)post('name')),'user'=>trim((string)post('user')),'password'=>(string)post('password')];
 if($n['password']==='' && !empty($c['password']))$n['password']=$c['password'];
 [$ok,$msg]=test_db($n);
 if($ok && post('save')==='1'){save_runtime_config($n,app_config()['app_url']);$c=$n;$msg='Database configuration saved.';}
}
$title='Database Settings';require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Administration</span><h1>Database</h1><p>Update and verify the MySQL connection used by PocketCat.</p></div></div>
<?php if($msg):?><div class="flash info"><?=e($msg)?></div><?php endif;?>
<form class="card form-card" method="post"><?=csrf_field()?>
<label>Host<input name="host" value="<?=e($c['host'])?>" required></label>
<div class="grid2"><label>Port<input name="port" value="<?=e($c['port']?:'3306')?>" required></label><label>Database Name<input name="name" value="<?=e($c['name'])?>" required></label><label>Username<input name="user" value="<?=e($c['user'])?>" required></label><label>Password<input type="password" name="password" placeholder="<?=!empty($c['password'])?'Configured · enter only to replace':''?>"></label></div>
<div class="install-note">Database credentials stay on the server. A successful test is required before saving a new connection.</div><br><button class="btn" name="save" value="1">Test & Save</button></form>
<?php require __DIR__.'/../includes/footer.php'; ?>
