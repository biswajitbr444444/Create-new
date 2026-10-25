<?php
require __DIR__.'/../bootstrap.php';
require_admin();

$root=dirname(__DIR__);
$versionFile=$root.'/VERSION';
$currentVersion=trim((string)@file_get_contents($versionFile));
if($currentVersion===''){$currentVersion='19.2';}
$releaseFile=$root.'/CHANGELOG-V19.70-PLAN-ALLOCATION-VALIDATION-FIX.md';
$releaseText=is_file($releaseFile)?trim((string)@file_get_contents($releaseFile)):'';
$releaseDate='2026-10-20';
$title='Version Details';
require __DIR__.'/../includes/header.php';
?>
<div class="page-head version-details-head">
  <div>
    <span class="eyebrow">Administration · Release Management</span>
    <h1>Version details</h1>
    <p>Current PocketCat release fixes plan allocation validation so valid TiB-sized storage quotas can be saved consistently through both admin submission paths.</p>
  </div>
  <div class="version-current-badge"><span>Current version</span><strong>V<?=e($currentVersion)?></strong></div>
</div>
<section class="card version-release-card">
  <div class="page-head">
    <div>
      <span class="eyebrow">Current release</span>
      <h2>PocketCat V<?=e($currentVersion)?></h2>
      <p class="muted">Release details are bundled with this deployment so the admin panel remains self-documenting.</p>
    </div>
    <span class="planned release-live">CURRENT</span>
  </div>
  <div class="version-meta-grid">
    <div><span>Version</span><strong>V<?=e($currentVersion)?></strong></div>
    <div><span>Release date</span><strong><?=e($releaseDate)?></strong></div>
    <div><span>Release file</span><strong><?=e(basename($releaseFile))?></strong></div>
    <div><span>Scope</span><strong>V19.69 → V19.70</strong></div>
  </div>
</section>
<section class="card version-changes-card">
  <div class="page-head">
    <div><span class="eyebrow">V19.69 → V19.70</span><h2>All V19.70 changes</h2><p class="muted">Single-source plan catalogue synchronization, secure admin save API, consistent feature lists, signup policy binding, and responsive pricing cards.</p></div>
    <a class="btn small secondary" href="/admin/">Admin Dashboard</a>
  </div>
  <?php if($releaseText!==''): ?>
    <pre class="version-notes"><?=e($releaseText)?></pre>
  <?php else: ?>
    <p class="muted">V19.70 release notes are not available.</p>
  <?php endif; ?>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
