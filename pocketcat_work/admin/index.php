<?php
require __DIR__.'/../bootstrap.php';require_admin();
$users=(int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn(); ensure_cms_schema(); $cmsPosts=(int)db()->query("SELECT COUNT(*) FROM cms_posts WHERE post_type='post' AND status<>'trash'")->fetchColumn(); $cmsPages=(int)db()->query("SELECT COUNT(*) FROM cms_posts WHERE post_type='page' AND status<>'trash'")->fetchColumn(); $cmsPending=(int)db()->query("SELECT COUNT(*) FROM cms_comments WHERE status='pending'")->fetchColumn(); $cmsApproved=(int)db()->query("SELECT COUNT(*) FROM cms_comments WHERE status='approved'")->fetchColumn();
$active=(int)db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$tasks=(int)db()->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$projects=(int)db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();$platform=platform_stats();
$months=[];$base=(int)setting('growth_base','10000');$rates=[0,.05,.10,.15,.25,.10];
for($i=5;$i>=0;$i--){$dt=new DateTime("first day of -{$i} month");$months[]=['label'=>$i===0?'Latest':$dt->format('M'), 'value'=>round($base)];$base*=1+$rates[5-$i];}if(setting('latest_month_linked','1')==='1'&&$months)$months[count($months)-1]['value']=$platform['latest_month_users'];
$max=max(array_column($months,'value'));
$title='Admin Dashboard';require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Administration</span><h1>Control center</h1><p>Manage PocketCat, users, services and security from one place.</p></div><a class="btn" href="/admin/settings/">Site settings</a></div>
<div class="stats">
  <div class="card stat"><span>CMS posts</span><strong><?=$cmsPosts?></strong></div><div class="card stat"><span>CMS pages</span><strong><?=$cmsPages?></strong></div><div class="card stat"><span>Pending comments</span><strong><?=$cmsPending?></strong></div>
  <div class="card stat"><span>Total users</span><strong><?=$users?></strong></div>
  <div class="card stat"><span>Active users</span><strong><?=$active?></strong></div>
  <div class="card stat"><span>Total tasks</span><strong><?=$tasks?></strong></div>
  <div class="card stat"><span>Projects</span><strong><?=$projects?></strong></div><div class="card stat"><span>Platform tasks</span><strong><?=number_format($platform['tasks'])?></strong></div>
</div>
<div class="grid-main">
<section class="card">
  <div class="page-head"><div><h2>6-month user growth</h2><p class="muted">Demo projection for presentation; actual users are shown in the cards above.</p></div></div>
  <div class="chart-wrap">
    <?php foreach($months as $m):$h=max(8,round($m['value']/$max*100));?>
      <div class="chart-col"><span class="chart-value"><?=number_format($m['value'])?></span><div class="bar" style="height:<?=$h?>%"></div><span class="chart-label"><?=$m['label']?></span></div>
    <?php endforeach;?>
  </div>
  <small class="muted">Starting point: 10,000 users · subsequent sample growth: 5%, 10%, 15%, 25%, 10%.</small>
</section>
<section class="card"><h2>Admin tools</h2><div class="quick">
<a href="/admin/users/">Manage users</a><a href="/admin/ai/">AWS Bedrock</a><a href="/admin/platform-activity/">Platform Activity</a><a href="/admin/email/">Email & SMTP</a><a href="/admin/database/">Database</a><a href="/admin/security/">Security</a><a href="/admin/version-details/">Version Details</a><a href="/admin/ota-updater/">OTA Update Center</a><a href="/admin/control-plane/">Super Admin Control Plane</a><a href="/admin/visual-engine/">Visual Command Center</a><a href="/admin/menus/">Navigation Menus</a><a href="/admin/messages/">Contact messages</a><a href="/admin/docs/">Documentation CMS</a><a href="/admin/posts/">Posts CMS</a><a href="/admin/pages/">Pages CMS</a><a href="/admin/categories/">Categories</a><a href="/admin/tags/">Tags</a><a href="/admin/comments/">Comments</a>
</div></section>
</div>
<div class="grid-main cms-dashboard-widgets"><section class="card"><div class="page-head"><div><h2>Recent content</h2><p class="muted">Latest CMS posts and pages.</p></div><a class="btn small secondary" href="/admin/posts/">View posts</a></div><div class="dashboard-list"><?php $recentCms=db()->query("SELECT id,post_type,title,status,updated_at FROM cms_posts WHERE status<>'trash' ORDER BY updated_at DESC LIMIT 6")->fetchAll(); foreach($recentCms as $rc):?><div class="dashboard-list-row"><div><strong><?=e($rc['title']?:'(Untitled)')?></strong><small><?=e(ucfirst($rc['post_type']))?> · <?=e(ucfirst($rc['status']))?></small></div><a href="/admin/<?=e($rc['post_type'])?>s/edit/?id=<?=$rc['id']?>">Edit</a></div><?php endforeach;if(!$recentCms):?><p class="muted">No CMS content yet.</p><?php endif;?></div></section><section class="card"><div class="page-head"><div><h2>Comments overview</h2><p class="muted">Moderation queue and approved discussion.</p></div><a class="btn small secondary" href="/admin/comments/">Moderate</a></div><div class="stats mini-stats"><div><span>Pending</span><strong><?=$cmsPending?></strong></div><div><span>Approved</span><strong><?=$cmsApproved?></strong></div></div></section></div>
<div class="admin-grid">
<a class="card" href="/admin/plans/"><div class="icon-box">$</div><h3>Subscription Plans</h3><p>Manage tier names, pricing, lifecycle, features and limits.</p></a><a class="card" href="/admin/redirects/"><div class="icon-box">↗</div><h3>Redirect Management</h3><p>Create, edit, disable and track short-link redirects.</p></a><a class="card" href="/admin/site-editor/"><div class="icon-box">✎</div><h3>Live Site Editor</h3><p>Edit public pages, theme, footer and social links.</p></a><a class="card" href="/admin/menus/"><div class="icon-box">☰</div><h3>Navigation Menus</h3><p>Manage header, footer and sidebar menus with nested three-dot actions.</p></a>
<a class="card" href="/admin/ai/"><div class="icon-box">AI</div><h3>Amazon Bedrock</h3><p>API key, region and model configuration.</p></a>
<a class="card" href="/admin/email/"><div class="icon-box">@</div><h3>Email delivery</h3><p>SMTP configuration and provider notes.</p></a>
<a class="card" href="/admin/security/"><div class="icon-box">✓</div><h3>Security</h3><p>Review application security controls.</p></a><a class="card" href="/admin/version-details/"><div class="icon-box">V</div><h3>Version Details</h3><p>View the active V19.70 release notes, plan catalogue synchronization and deployment changes.</p></a>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
