<?php
require __DIR__.'/../bootstrap.php';
require_login();
$docs=docs_get_all();
$title='Help & Documentation';
require __DIR__.'/../includes/header.php';
?><meta name="robots" content="noindex,nofollow,noarchive">
<link rel="stylesheet" href="/assets/css/help-docs.css">
<div class="pc-docs-shell">
 <aside class="pc-docs-sidebar">
  <div class="pc-docs-brand"><span class="pc-docs-logo">?</span><div><strong>PocketCat Docs</strong><small>Private Help & Documentation</small></div></div>
  <div class="pc-docs-private">Private · Login required · Not indexed</div>
  <label class="pc-doc-search"><span>⌕</span><input id="pc-doc-search" type="search" placeholder="Search documentation…"></label>
  <nav id="pc-doc-nav"><?php foreach($docs as $d): ?><a href="#<?=e($d['slug'])?>" data-doc-title="<?=e(strtolower($d['title'].' '.$d['summary'].' '.$d['section']))?>"><span><?=e($d['title'])?></span></a><?php endforeach; ?></nav>
 </aside>
 <main class="pc-docs-main">
  <header class="pc-docs-hero"><span class="eyebrow">POCKETCAT PRIVATE HELP CENTER</span><h1>Help & Documentation</h1><p>Contextual guides for PocketCat features and every documented input field. Entries are private, searchable here, and managed by authorized administrators.</p><div class="pc-doc-chips"><span>Step-by-step</span><span>Examples</span><span>Troubleshooting</span><span>Official resources</span></div></header>
  <div class="pc-docs-content" id="pc-doc-content">
   <?php foreach($docs as $d): $steps=docs_decode_list($d['steps_json']);$tr=docs_decode_list($d['troubleshooting_json']);$official=docs_decode_official($d['official_json']); ?>
   <article class="pc-doc-section" id="<?=e($d['slug'])?>" data-doc-search="<?=e(strtolower($d['title'].' '.$d['summary'].' '.$d['section'].' '.$d['overview'].' '.$d['requirements']))?>">
    <div class="pc-doc-kicker"><?=e($d['section'])?></div><h2><?=e($d['title'])?></h2><p class="pc-doc-summary"><?=e($d['summary'])?></p>
    <section><h3>Overview</h3><div class="pc-doc-copy"><?=nl2br(e($d['overview']))?></div></section>
    <section><h3>Requirements</h3><div class="pc-doc-copy"><?=nl2br(e($d['requirements']))?></div></section>
    <?php if($steps): ?><section><h3>Step-by-Step</h3><ol><?php foreach($steps as $s): ?><li><?=e($s)?></li><?php endforeach; ?></ol></section><?php endif; ?><?php if($steps): ?><section><h3>Visual Configuration</h3><div class="pc-doc-visual"><?php foreach(array_slice($steps,0,5) as $i=>$s): ?><div class="pc-doc-visual-step"><b><?=e((string)($i+1))?></b><span><?=e($s)?></span></div><?php endforeach; ?></div></section><?php endif; ?>
    <?php if(trim($d['examples_html'])!==''): ?><section><h3>Live Example & Format</h3><div class="pc-doc-example"><?=docs_clean_html($d['examples_html'])?></div></section><?php endif; ?>
    <?php if($tr): ?><section><h3>Troubleshooting</h3><ul><?php foreach($tr as $s): ?><li><?=e($s)?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if($official): ?><section><h3>Official Resources</h3><div class="pc-doc-links"><?php foreach($official as $l): if(!is_array($l)||count($l)<2)continue; ?><a href="<?=e($l[1])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e($l[0])?> ↗</a><?php endforeach; ?></div></section><?php endif; ?>
    <a class="pc-doc-top" href="#top">Back to top ↑</a>
   </article>
   <?php endforeach; ?>
  </div>
 </main>
</div>
<script src="/assets/js/help-docs.js" defer></script>
<?php require __DIR__.'/../includes/footer.php'; ?>
