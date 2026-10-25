<?php
require __DIR__.'/../bootstrap.php'; require_admin();
$editId=(int)getv('edit','0');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $action=(string)post('action','save');
        if($action==='delete'){docs_delete((int)post('id'));flash('success','Documentation entry deleted.');redirect('/admin/docs/');}
        docs_save($_POST,$editId?:null);flash('success',$editId?'Documentation entry updated.':'Documentation entry created.');redirect('/admin/docs/');
    }catch(Throwable $e){flash('error',$e->getMessage());}
}
$rows=docs_get_all();$editing=$editId?docs_get_by_id($editId):null;
$title='Documentation CMS';require __DIR__.'/../includes/header.php';
?><div class="page-head"><div><span class="eyebrow">PRIVATE CONTENT</span><h1>Documentation CMS</h1><p>Create and maintain contextual feature and field documentation. Entries are private and require login to view.</p></div><a class="btn secondary" href="/docs/">Open Docs</a></div>
<section class="card form-card"><h2><?= $editing?'Edit documentation':'Create documentation'?></h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save">
<div class="grid2"><label>Slug<input name="slug" value="<?=e($editing['slug']??'')?>" placeholder="ai-bedrock" required></label><label>Title<input name="title" value="<?=e($editing['title']??'')?>" required></label><label>Section<input name="section" value="<?=e($editing['section']??'General')?>" required></label><label>Summary<input name="summary" value="<?=e($editing['summary']??'')?>" required></label></div>
<label>Overview<textarea name="overview" rows="5" required><?=e($editing['overview']??'')?></textarea></label>
<label>Requirements<textarea name="requirements" rows="5"><?=e($editing['requirements']??'')?></textarea></label>
<label>Steps — one step per line<textarea name="steps" rows="7"><?=e($editing?docs_steps_text($editing['steps_json']):'')?></textarea></label>
<label>Examples / mock output — safe HTML only<textarea name="examples" rows="6" placeholder="Model ID: <code>example-model</code>"><?=e($editing['examples_html']??'')?></textarea></label>
<label>Troubleshooting — one item per line<textarea name="troubleshooting" rows="6"><?=e($editing?implode("\n",docs_decode_list($editing['troubleshooting_json'])):'')?></textarea></label>
<label>Official resources — one <code>Label | https://official.example/</code> per line<textarea name="official" rows="5"><?=e($editing?docs_official_text($editing['official_json']):'')?></textarea></label>
<button class="btn">Save Documentation</button> <?php if($editing): ?><a class="btn secondary" href="/admin/docs/">Cancel</a><?php endif; ?></form></section>
<section class="card"><h2>Documentation entries</h2><div class="table-wrap"><table><thead><tr><th>Title</th><th>Section</th><th>Visibility</th><th>Updated</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?=e($r['title'])?></strong><br><small><?=e($r['slug'])?></small></td><td><?=e($r['section'])?></td><td><span class="badge">Private</span></td><td><?=e($r['updated_at'])?></td><td><a class="btn small" href="/admin/docs/?edit=<?=e((string)$r['id'])?>">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm('Delete this documentation entry?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e((string)$r['id'])?>"><button class="btn small danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
