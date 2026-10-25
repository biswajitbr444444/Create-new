<?php
require __DIR__.'/../bootstrap.php';
require_admin();

$msg='';
$me=current_user();
$myId=(int)($me['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $uid=(int)post('id',0);
    $action=(string)post('action','');

    if($uid<=0){
        flash('error','Invalid user selected.');
        redirect('/admin/users/');
    }

    try{
        $q=db()->prepare('SELECT id,full_name,email,username,role,plan,status FROM users WHERE id=? LIMIT 1');
        $q->execute([$uid]);
        $target=$q->fetch();
        if(!$target){
            flash('error','User not found.');
            redirect('/admin/users/');
        }

        // Never allow the last administrator to be removed or disabled.
        if($uid===$myId && in_array($action,['toggle','role','delete'],true)){
            flash('error','Your own administrator role/status is protected. Use another administrator account to change it.');
            redirect('/admin/users/');
        }

        if($action==='toggle'){
            $newStatus=$target['status']==='active'?'disabled':'active';
            $s=db()->prepare('UPDATE users SET status=?,updated_at=NOW() WHERE id=?');
            $s->execute([$newStatus,$uid]);
            flash('success',$newStatus==='active'?'User activated successfully.':'User disabled successfully.');
        }elseif($action==='role'){
            $newRole=$target['role']==='admin'?'user':'admin';
            if($newRole==='user'){
                $count=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
                if($count<=1){
                    flash('error','The account cannot be demoted because it is the last active administrator.');
                    redirect('/admin/users/');
                }
            }
            $s=db()->prepare('UPDATE users SET role=?,updated_at=NOW() WHERE id=?');
            $s->execute([$newRole,$uid]);
            flash('success',$newRole==='admin'?'User is now an administrator.':'Administrator role removed.');
        }elseif($action==='delete'){
            if($uid===$myId){flash('error','You cannot delete your own administrator account.');redirect('/admin/users/');}
            if($target['role']==='admin'){
                $count=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
                if($count<=1){flash('error','The last active administrator cannot be deleted.');redirect('/admin/users/');}
            }
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            flash('success','User deleted successfully.');
        }elseif($action==='plan'){
            $plan=(string)post('plan','free');
            if(!in_array($plan,['free','basic','premium'],true)){
                flash('error','Invalid plan selected.');
                redirect('/admin/users/');
            }
            $s=db()->prepare('UPDATE users SET plan=?,updated_at=NOW() WHERE id=?');
            $s->execute([$plan,$uid]);
            flash('success','User plan changed to '.ucfirst($plan).'.');
        }else{
            flash('error','Unknown user action.');
        }
    }catch(Throwable $e){
        flash('error','User update failed: '.$e->getMessage());
    }
    redirect('/admin/users/');
}

$userSearch=trim((string)getv('q',''));
if($userSearch!==''){$like='%'.$userSearch.'%';$st=db()->prepare("SELECT id,full_name,email,username,role,plan,status,created_at FROM users WHERE full_name LIKE ? OR email LIKE ? OR username LIKE ? OR role LIKE ? OR plan LIKE ? OR status LIKE ? ORDER BY id DESC");$st->execute([$like,$like,$like,$like,$like,$like]);$users=$st->fetchAll();}
else{$users=db()->query("SELECT id,full_name,email,username,role,plan,status,created_at FROM users ORDER BY id DESC")->fetchAll();}
function user_search_mark(string $text,string $q):string{$safe=e($text);if($q==='')return $safe;return preg_replace('/('.preg_quote(e($q),'/').')/iu','<mark class="user-search-match">$1</mark>',$safe)??$safe;}
$adminCount=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
$title='Users';require __DIR__.'/../includes/header.php';
?>
<style>.user-search-wrap{position:relative;margin-bottom:16px}.user-search-form{display:flex;gap:8px;align-items:center}.user-search-form input{flex:1;padding-right:78px}.user-search-kbd{position:absolute;right:88px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--muted);background:var(--bg);border:1px solid var(--line);border-radius:5px;padding:3px 6px;pointer-events:none}.user-search-match{background:#fff19a;color:#111827;border-radius:3px;padding:1px 2px}.user-search-hit{animation:userSearchHit 10s ease-out both}@keyframes userSearchHit{0%,100%{background:transparent}8%,35%{background:#fff3a6;box-shadow:0 0 0 3px rgba(245,190,0,.18)}}.user-search-empty{padding:18px;text-align:center;color:var(--muted)}@media(max-width:620px){.user-search-kbd{display:none}.user-search-form{align-items:stretch}.user-search-form input{min-width:0}}</style>
<div class="page-head">
  <div><span class="eyebrow">Administration</span><h1>Users</h1><p>Manage account status, administrator roles and Free/Basic/Premium plans.</p></div>
  <a class="btn" href="/admin/user-create/">Create user</a>
</div>
<div class="card user-search-wrap"><form class="user-search-form" method="get" action="/admin/users/" role="search"><input id="userSearchInput" type="search" name="q" value="<?=e($userSearch)?>" placeholder="Search users by name, username, email, role or plan…" autocomplete="off" aria-label="Search users"><kbd class="user-search-kbd">Ctrl K</kbd><button class="btn" type="submit">Search</button><?php if($userSearch!==''): ?><a class="btn secondary" href="/admin/users/">Clear</a><?php endif; ?></form></div>
<div class="card install-note" style="margin-bottom:18px">
  <strong>Administrator safety:</strong> your own administrator account cannot be disabled or demoted. Other users can be promoted to admin. The last active administrator also cannot be demoted.
  <span class="muted"> Active administrators: <?=number_format($adminCount)?></span>
</div>
<div class="card table-wrap"><?php if($userSearch!==''&&!$users): ?><div class="user-search-empty">No user found for <strong><?=e($userSearch)?></strong>.</div><?php else: ?><table><tr><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Plan</th><th>Actions</th></tr>
<?php foreach($users as $x):$self=((int)$x['id']===$myId);?>
<tr class="<?= $userSearch!==''?'user-search-hit':'' ?>">
<td><?=user_search_mark($x['full_name'],$userSearch)?><?=$self?' <span class="badge">You</span>':''?></td>
<td><?=user_search_mark($x['email'],$userSearch)?></td>
<td><?=user_search_mark($x['username'],$userSearch)?></td>
<td><span class="badge"><?=user_search_mark($x['role'],$userSearch)?></span></td>
<td><span class="badge"><?=user_search_mark($x['status'],$userSearch)?></span></td>
<td><span class="badge"><?=user_search_mark($x['plan']??'free',$userSearch)?></span></td>
<td><div class="actions">
<?php if(!$self):?>
<form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$x['id']?>"><input type="hidden" name="action" value="toggle"><button class="btn small secondary"><?= $x['status']==='active'?'Disable':'Activate' ?></button></form>
<form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$x['id']?>"><input type="hidden" name="action" value="role"><button class="btn small secondary"><?= $x['role']==='admin'?'Make User':'Make Admin' ?></button></form>
<?php endif;?>
<form method="post" class="actions"><?=csrf_field()?><input type="hidden" name="id" value="<?=$x['id']?>"><input type="hidden" name="action" value="plan"><select name="plan" onchange="this.form.submit()"><option value="free" <?=$x['plan']==='free'?'selected':''?>>Free</option><option value="basic" <?=($x['plan']??'free')==='basic'?'selected':''?>>Basic</option><option value="premium" <?=$x['plan']==='premium'?'selected':''?>>Premium</option></select></form><?php if(!$self): ?><form method="post" onsubmit="return confirm('Delete this user permanently?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$x['id']?>"><input type="hidden" name="action" value="delete"><button class="btn small danger" type="submit">Delete</button></form><?php endif;?>
</div></td></tr>
<?php endforeach;?></table><?php endif; ?></div>
<script>(function(){const input=document.getElementById('userSearchInput');if(!input)return;const k=document.querySelector('.user-search-kbd');const mac=/Mac|iPhone|iPad/i.test(navigator.platform);if(k)k.textContent=mac?'⌘ K':'Ctrl K';document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();input.focus();input.select()}else if(e.key==='/'&&document.activeElement!==input&&!/input|textarea|select/i.test(document.activeElement?.tagName||'')){e.preventDefault();input.focus()}else if(e.key==='Escape'&&document.activeElement===input){input.value=''}});const hit=document.querySelector('.user-search-hit');if(hit)hit.scrollIntoView({behavior:'smooth',block:'center'});})();</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
