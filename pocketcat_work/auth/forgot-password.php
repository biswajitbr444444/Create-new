<?php
require __DIR__.'/../bootstrap.php';
$msg='';$sent=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$email=trim((string)post('email'));
 if(!valid_email($email)){$msg='Enter a valid account email.';}else{
   $s=db()->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");$s->execute([$email]);$u=$s->fetch();
   if($u){$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$s=db()->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())');$s->execute([$u['id'],$hash]);
      $link=rtrim((string)(setting('app_url',app_config()['app_url']??'')), '/').'/reset-password/?token='.urlencode($token);
      $mail=smtp_send($email,'PocketCat password reset','Use this link within one hour: '.$link);
      $sent=true;
      if(!$mail[0])$msg='A reset token was created, but email delivery is not configured or verified yet. Configure SMTP in Admin → Email & SMTP, then request a new reset link.';
   }else{$sent=true;}
 }
}
public_header('Forgot Password');
?>
<section class="center-card"><span class="eyebrow">Account recovery</span><h1>Reset your password</h1><p class="sub">Enter the email associated with your PocketCat account. If the account exists, a one-hour reset token will be created.</p><?php if($msg):?><div class="flash <?= $sent?'info':'error'?>"><?=e($msg)?></div><?php endif;?><?php if($sent&&!$msg):?><div class="flash success">If the account exists, a reset link has been sent.</div><?php endif;?><form method="post"><?=csrf_field()?><label>Email<input type="email" name="email" required></label><button class="btn full">Create Reset Link</button></form><p><a href="/login/">Back to Login</a></p></section><?php public_footer(); ?>