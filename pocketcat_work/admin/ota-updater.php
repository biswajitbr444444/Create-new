<?php
require __DIR__.'/../bootstrap.php';
require_admin();
require_once __DIR__.'/../includes/ota-updater.php';
$title='OTA Update Center';
try { $recoveryResults=ota_recover_interrupted(); } catch(Throwable $recoveryError) { $recoveryResults=[]; error_log('[PocketCat OTA recovery] '.$recoveryError->getMessage()); }
$errorDetails=''; $success=''; $preview=null; $stage=null; $package=null; $action='';
try { $otaStore=ota_storage_root(); } catch(Throwable $e) { $otaStore=dirname(__DIR__).'/storage'; $errorDetails=$e->getMessage(); }
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        $action=(string)post('action','');
        if($action==='stage') {
            if(empty($_FILES['update_package']) || !is_uploaded_file($_FILES['update_package']['tmp_name'])) throw new RuntimeException('Choose an update archive to upload.');
            $up=$_FILES['update_package']; if((int)$up['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Upload failed with PHP upload error code '.(int)$up['error'].'. Check upload_max_filesize, post_max_size and available disk space.');
            if((int)$up['size']<1 || (int)$up['size']>100*1024*1024) throw new RuntimeException('Update package must be between 1 byte and 100 MB.');
            $id=gmdate('YmdHis').'-'.bin2hex(random_bytes(5)); $stage=$otaStore.'/staging/'.$id;
            if(!is_dir(dirname($stage)) && !mkdir(dirname($stage),0750,true)) throw new RuntimeException('Could not create private staging directory.');
            $package=$otaStore.'/staging/'.$id.'-package-'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename((string)$up['name']));
            if(!move_uploaded_file($up['tmp_name'],$package)) throw new RuntimeException('Could not move the uploaded package into private staging storage.');
            $info=ota_check_archive($package); ota_extract($package,$info,$stage); ota_flatten_stage($stage); $files=ota_collect_files($stage); $migrations=ota_validate_migrations($stage);
            if(!$files) throw new RuntimeException('No eligible files were found. Protected configuration, storage, uploads and session paths are never updated.');
            $_SESSION['ota_pending']=['id'=>$id,'stage'=>$stage,'package'=>$package,'format'=>$info['format'],'files'=>$files,'migrations'=>array_keys($migrations),'created'=>time()];
            $preview=$_SESSION['ota_pending']; $success='Package validated and staged. No live files have been changed yet.';
        } elseif($action==='apply') {
            $pending=$_SESSION['ota_pending']??null; if(!is_array($pending)||empty($pending['stage'])||time()-(int)($pending['created']??0)>1800||!is_dir((string)$pending['stage'])) throw new RuntimeException('The staged package expired. Upload and validate it again.');
            if(!hash_equals((string)($pending['id']??''),(string)post('package_id',''))) throw new RuntimeException('Staged package token did not match.');
            $result=ota_apply_stage($pending['stage'],$pending['id']); unset($_SESSION['ota_pending']); @unlink($pending['package']); if(is_dir($pending['stage'])){ $cleanup=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pending['stage'],FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($cleanup as $cf){$cf->isDir()?@rmdir($cf->getPathname()):@unlink($cf->getPathname());} @rmdir($pending['stage']); }
            $success='V19.2 applied successfully. '.count($result['changed']).' file(s) changed; '.count($result['migrations']).' additive migration file(s) processed. Rollback snapshot: '.basename($result['backup']);
            try { log_event('ota.update.applied',(int)(current_user()['id']??0)); } catch(Throwable $ignore) {}
        } elseif($action==='discard') { $pending=$_SESSION['ota_pending']??null; if(is_array($pending)){ if(!empty($pending['stage'])&&is_dir($pending['stage'])){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pending['stage'],FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());} @rmdir($pending['stage']); } if(!empty($pending['package']))@unlink($pending['package']); } unset($_SESSION['ota_pending']); $success='Staged update discarded. Live files were not changed.'; }
        $preview=$_SESSION['ota_pending']??null;
    } catch(Throwable $e) {
        if ($action==='stage') {
            if (is_string($stage) && is_dir($stage)) { $cleanup=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($cleanup as $cf){$cf->isDir()?@rmdir($cf->getPathname()):@unlink($cf->getPathname());} @rmdir($stage); }
            if (is_string($package) && is_file($package)) @unlink($package);
        }
        $errorDetails='['.get_class($e).'] '.$e->getMessage()."\nError code: ".$e->getCode()."\nFile: ".$e->getFile()."\nLine: ".$e->getLine()."\nTime: ".gmdate('c')."\nPHP: ".PHP_VERSION."\nSAPI: ".PHP_SAPI."\nDocument root: ".(string)($_SERVER['DOCUMENT_ROOT']??'unavailable')."\nStack trace:\n".$e->getTraceAsString()."\n";
        error_log('[PocketCat OTA] '.$errorDetails.$e->getTraceAsString()); $preview=$_SESSION['ota_pending']??null;
    }
}
if(!$preview && isset($_SESSION['ota_pending']) && is_array($_SESSION['ota_pending']) && time()-(int)($_SESSION['ota_pending']['created']??0)<=1800) $preview=$_SESSION['ota_pending'];
$currentVersion=trim((string)@file_get_contents(ota_root().'/VERSION')) ?: 'unknown';
require __DIR__.'/../includes/header.php';
?>
<style>
.ota-wrap{max-width:1100px;margin:0 auto}.ota-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}.ota-pill{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:7px 11px;background:#e8f7ef;color:#176b45;font-size:12px;font-weight:700}.ota-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}.ota-metric{padding:17px;border:1px solid var(--border,#e5e7eb);border-radius:14px;background:var(--card,#fff)}.ota-metric span{display:block;color:var(--muted,#6b7280);font-size:12px}.ota-metric strong{display:block;font-size:20px;margin-top:7px;overflow-wrap:anywhere}.ota-form-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);gap:16px}.ota-drop{border:1.5px dashed var(--border,#cbd5e1);border-radius:14px;padding:20px;background:var(--surface,#f8fafc)}.ota-error{border:1px solid #f2a7a7;background:#fff4f4;color:#7f1d1d}.ota-error pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;max-height:360px;overflow:auto}.ota-file-list{max-height:300px;overflow:auto;border:1px solid var(--border,#e5e7eb);border-radius:10px}.ota-file-list li{padding:9px 12px;border-bottom:1px solid var(--border,#e5e7eb);overflow-wrap:anywhere;font-size:13px}.ota-note{font-size:13px;color:var(--muted,#64748b)}.ota-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:15px}@media(max-width:760px){.ota-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ota-form-grid{grid-template-columns:1fr}.ota-metric strong{font-size:18px}}
</style>
<div class="ota-wrap">
  <?php if(!empty($recoveryResults)):?><div class="card" role="status" style="margin-bottom:16px"><strong>Interrupted deployment recovery ran.</strong><?php foreach($recoveryResults as $rr):?><p>Snapshot <?=e($rr['id'])?>: <?=empty($rr['errors'])?'previous files restored':('manual attention needed for '.e(implode(', ',$rr['errors'])))?>.</p><?php endforeach;?></div><?php endif;?>
  <div class="page-head ota-hero"><div><span class="eyebrow">Administration · Release Management</span><h1>OTA Update Center</h1><p>Validate an update package, review its impact, then deploy with a file snapshot and automatic rollback on detected failures.</p></div><span class="ota-pill">● Protected deployment</span></div>
  <?php if($success!==''):?><div class="card" role="status" style="border-color:#9ad8b5;background:#effbf4;margin-bottom:16px"><strong><?=e($success)?></strong></div><?php endif;?>
  <section class="ota-grid"><div class="ota-metric"><span>Installed version</span><strong>V<?=e($currentVersion)?></strong></div><div class="ota-metric"><span>Archive formats</span><strong>10 recognized</strong></div><div class="ota-metric"><span>Deployment mode</span><strong>Staged + rollback</strong></div></section>
  <div class="ota-form-grid">
    <section class="card"><h2>Upload update package</h2><p class="ota-note">Supported: ZIP, TAR, TAR.GZ/TGZ, TAR.BZ2/TBZ2, 7Z, RAR, TAR.XZ/TXZ, GZ, BZ2 and XZ. Some formats require a matching server utility.</p>
      <form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="stage"><label for="update_package">Release archive</label><div class="ota-drop"><input id="update_package" name="update_package" type="file" required accept=".zip,.tar,.gz,.tgz,.bz2,.tbz2,.7z,.rar,.xz,.txz"><p class="ota-note">Maximum compressed size: 100 MB. Expanded contents are capped at 250 MB / 10,000 files.</p></div><div class="ota-actions"><button class="btn" type="submit">Validate &amp; Stage</button></div></form>
      <div class="ota-note" style="margin-top:16px"><strong>Safety rules:</strong> `.env`, config, database credentials, sessions, storage, user uploads and runtime secrets are excluded from deployment. Symlinks and path traversal entries are rejected.</div>
    </section>
    <section class="card"><h2>Deployment safeguards</h2><ul><li>Archive integrity and path validation before extraction.</li><li>Changed-file-only writes; unchanged files are skipped.</li><li>Backups of every affected existing file before replacement.</li><li>PHP syntax validation after each PHP file is written.</li><li>Automatic file rollback if deployment or validation fails.</li><li>Only additive SQL migrations are accepted.</li></ul><p class="ota-note">Database changes are not destructively rolled back. Approved migrations may add tables, columns or indexes; they cannot drop or wipe existing data.</p></section>
  </div>
  <?php if($errorDetails!==''):?><section class="card ota-error" id="ota-error-details" style="margin-top:16px"><h2>Error Details</h2><p>Deployment did not complete successfully. Review or copy the diagnostics below. Sensitive credentials are not intentionally included.</p><pre id="ota-error-log"><?=e($errorDetails)?></pre><button class="btn secondary" type="button" id="ota-copy-error">Copy to Clipboard</button><span id="ota-copy-status" class="ota-note" aria-live="polite"></span></section><?php endif;?>
  <?php if(is_array($preview)):?><section class="card" style="margin-top:16px"><div class="page-head"><div><span class="eyebrow">Pre-deployment review</span><h2>Package ready to apply</h2><p class="ota-note">Format: <?=e($preview['format']??'Archive')?> · Review the paths below before applying.</p></div><span class="ota-pill">Validated</span></div>
    <?php $pendingFiles=is_array($preview['files']??null)?$preview['files']:[]; $eligible=array_keys($pendingFiles); ?><div class="ota-grid"><div class="ota-metric"><span>Eligible files</span><strong><?=count($eligible)?></strong></div><div class="ota-metric"><span>Estimated size</span><strong><?=e(number_format(array_sum(array_map(fn($f)=>(int)($f['size']??0),$pendingFiles))/1024,1))?> KB</strong></div><div class="ota-metric"><span>Migration files</span><strong><?=count($preview['migrations']??[])?></strong></div></div>
    <div class="ota-file-list"><ul style="list-style:none;margin:0;padding:0"><?php foreach(array_slice($eligible,0,500) as $path):?><li><code><?=e($path)?></code><span class="ota-note"> · <?=e(number_format((int)$pendingFiles[$path]['size']))?> bytes</span></li><?php endforeach;?></ul></div><?php if(count($eligible)>500):?><p class="ota-note">Showing first 500 of <?=count($eligible)?> files.</p><?php endif;?>
    <?php if(!empty($preview['migrations'])):?><p class="ota-note"><strong>Migration files:</strong> <?=e(implode(', ',$preview['migrations']))?></p><?php endif;?>
    <div class="ota-actions"><form method="post" onsubmit="return confirm('Apply this update now? A backup is created first, but a hosting crash or power loss can still require manual recovery.');"><?=csrf_field()?><input type="hidden" name="action" value="apply"><input type="hidden" name="package_id" value="<?=e($preview['id'])?>"><button class="btn" type="submit">Apply V19.2 Update</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="discard"><button class="btn secondary" type="submit">Discard staged package</button></form></div>
  </section><?php endif;?>

  <section class="card" style="margin-top:16px"><h2>Rollback snapshots</h2><p class="ota-note">Snapshots are stored under private <code>storage/ota/backups/</code>. Keep sufficient disk space and take a hosting-level backup before production deployments.</p><p class="ota-note">This updater cannot guarantee recovery from a host forcibly terminating PHP, disk failure, permission changes or an outage before rollback code executes. For critical sites, use a maintenance window and provider backup.</p></section>
</div>
<script>
(()=>{const b=document.getElementById('ota-copy-error');if(!b)return;b.addEventListener('click',async()=>{const text=document.getElementById('ota-error-log')?.innerText||'';const status=document.getElementById('ota-copy-status');try{if(navigator.clipboard&&window.isSecureContext)await navigator.clipboard.writeText(text);else{const t=document.createElement('textarea');t.value=text;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();const ok=document.execCommand('copy');t.remove();if(!ok)throw new Error('Copy command unavailable');}status.textContent='Copied diagnostics.';}catch(e){status.textContent='Clipboard unavailable. Select and copy the error text manually.';}});})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
