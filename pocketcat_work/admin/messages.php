<?php
require __DIR__.'/../bootstrap.php';require_admin();
$messages=db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll();
$title='Contact Messages';require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Administration</span><h1>Contact messages</h1><p>Messages submitted through the public contact form.</p></div></div>
<div class="card table-wrap"><table><tr><th>Date</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th></tr><?php foreach($messages as $m):?><tr><td><?=e($m['created_at'])?></td><td><?=e($m['name'])?></td><td><?=e($m['email'])?></td><td><?=e($m['subject'])?></td><td style="white-space:normal;min-width:300px"><?=nl2br(e($m['message']))?></td></tr><?php endforeach;?></table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
