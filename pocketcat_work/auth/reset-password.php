<?php
require __DIR__.'/../bootstrap.php';
$token=trim((string)getv('token'));$msg='';$ok=false;$row=null;
if($token!==''){ $s=db()->prepare("SELECT pr.id,pr.user_id FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>NOW() AND u.status='active' LIMIT 1");$s->execute([hash('sha256',$token)]);$row=$s->fetch(); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$token=trim((string)post('token'));$password=(string)post('password');$confirm=(string)post('confirm');
 $s=db()->prepare("SELECT pr.id,pr.user_id FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>NOW() AND u.status='active' LIMIT 1");$s->execute([hash('sha256',$token)]);$row=$s->fetch();
 if(!$row)$msg='This reset link is invalid or expired.';elseif(strlen($password)<10||$password!==$confirm)$msg='Use a matching password of at least 10 characters.';else{db()->prepare('UPDATE users SET password_hash=?,must_reset_password=0,updated_at=NOW() WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$row['user_id']]);db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$row['id']]);flash('success','Your password has been reset. Please sign in.');redirect('/login/');}
}
public_header('Reset Password');
?>
<section class="center-card"><span class="eyebrow">Account recovery</span><h1>Choose a new password</h1><?php if($msg):?><div class="flash error"><?=e($msg)?></div><?php endif;?><?php if($row||$token!==''):?><form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><label>New Password<input type="password" name="password" minlength="10" required></label><label>Confirm Password<input type="password" name="confirm" minlength="10" required></label><button class="btn full">Reset Password</button></form><?php else:?><p class="sub">Open the reset link from your email to continue.</p><?php endif;?></section><?php public_footer(); ?>