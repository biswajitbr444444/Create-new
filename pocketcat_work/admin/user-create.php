<?php
require __DIR__.'/../bootstrap.php';
require_admin();
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $name=trim((string)post('name',''));
    $email=trim((string)post('email',''));
    $username=trim((string)post('username',''));
    $password=(string)post('password','');
    $role=(string)post('role','user');
    $plan=(string)post('plan','free');
    if($name===''||!valid_email($email)||$username===''||strlen($password)<10){
        $msg='Enter a name, valid email, username and password of at least 10 characters.';
    }elseif(!in_array($role,['admin','user'],true)||!in_array($plan,['free','basic','premium'],true)){
        $msg='Invalid role or plan.';
    }else{
        try{
            $s=db()->prepare("INSERT INTO users(full_name,email,username,password_hash,role,plan,status,created_at,updated_at) VALUES(?,?,?,?,?,?, 'active', NOW(), NOW())");
            $s->execute([$name,$email,$username,password_hash($password,PASSWORD_DEFAULT),$role,$plan]);
            flash('success','User created successfully.');
            redirect('/admin/users/');
        }catch(Throwable $e){$msg='Could not create user. The email or username may already be in use.';}
    }
}
$title='Create User';require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Administration</span><h1>Create user</h1><p>Create a user directly from the admin panel for testing or onboarding.</p></div><a class="btn secondary" href="/admin/users/">Back to users</a></div>
<?php if($msg):?><div class="flash error"><?=e($msg)?></div><?php endif;?>
<form class="card form-card" method="post"><?=csrf_field()?><label>Full Name<input name="name" value="<?=e(post('name',''))?>" required></label><label>Email<input type="email" name="email" value="<?=e(post('email',''))?>" required></label><label>Username<input name="username" value="<?=e(post('username',''))?>" required></label><label>Password<input type="password" name="password" minlength="10" required></label><div class="grid2"><label>Role<select name="role"><option value="user">User</option><option value="admin">Admin</option></select></label><label>Plan<select name="plan"><option value="free">Free</option><option value="basic">Basic</option><option value="premium">Premium</option></select></label></div><button class="btn">Create User</button></form>
<?php require __DIR__.'/../includes/footer.php'; ?>
