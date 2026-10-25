<?php
require __DIR__.'/../bootstrap.php';
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim((string)post('name'));$email=trim((string)post('email'));$subject=trim((string)post('subject'));$message=trim((string)post('message'));
 if($name===''||!valid_email($email)||$subject===''||$message==='')$msg='Please complete all fields with a valid email address.';
 else{
   db()->prepare('INSERT INTO contact_messages(name,email,subject,message,ip_address,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$name,$email,$subject,$message,client_ip()]);
   flash('success','Thanks — your message has been received.');redirect('/contact/');
 }
}
public_header("Contact");
?>
<section class="page-hero"><span class="eyebrow">Contact</span><h1>Let’s talk about PocketCat.</h1><p>Questions, feedback, partnership ideas, installation issues or product suggestions are welcome. Use the form below to send a structured message to the PocketCat team.</p></section>
<section class="section contact-section"><div class="section-inner contact-shell"><div class="card contact-intro"><h2>Before you send</h2><p class="muted">For account support, include the email address associated with your PocketCat account. Never include passwords, AWS secret keys, API keys, SMTP passwords or other credentials in the contact form. For technical problems, describe what page you opened, what action you attempted and the visible error message.</p><p class="muted">Contact submissions are stored so an administrator can review and respond to them. This page is intended for support and product communication, not for transmitting confidential credentials.</p></div>
<div class="card"><h2>Send a message</h2><?php if($msg):?><div class="flash error"><?=e($msg)?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="grid2"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label></div><label>Subject<input name="subject" required></label><label>Message<textarea name="message" rows="10" required></textarea></label><button class="btn">Send Message</button></form></div>
<div><div class="card"><div class="icon-box">@</div><h3>Email</h3><p>support@pocketcat.online</p></div><div class="card" style="margin-top:18px"><div class="icon-box">IN</div><h3>Based in India</h3><p>PocketCat is an early-stage SaaS productivity product.</p></div></div>
</div></section>
<?php public_footer(); ?>
