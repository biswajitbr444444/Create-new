<?php
require __DIR__.'/../../bootstrap.php'; require_super_admin();
$location=(string)getv('location','header');if(!array_key_exists($location,menu_locations()))$location='header';
$m=menu_get($location);$title='Navigation Menus';
require __DIR__.'/../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/menu/manager.css">
<section class="card pc-menu-admin" id="pc-menu-manager">
  <div class="pc-menu-head"><div><span class="eyebrow">STRUCTURE</span><h1>Navigation Menu Manager</h1><p class="muted">Build Header, Footer and Sidebar navigation without drag-and-drop. Use ⋮ for every structural action.</p></div>
  <label>Menu Location<select id="pc-menu-location"><?php foreach(menu_locations() as $k=>$v):?><option value="<?=e($k)?>" <?=$k===$location?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label></div>
  <div class="pc-menu-layout">
    <aside class="pc-menu-sources" aria-label="Add menu items">
      <div class="pc-menu-accordion open"><button class="pc-acc" aria-expanded="true">Existing Pages <span>⌄</span></button><div class="pc-acc-body"><input id="pc-page-search" type="search" placeholder="Search pages"><div id="pc-pages-list" class="pc-source-list"></div><button class="btn small secondary" id="pc-pages-all">Select All</button><button class="btn small" id="pc-pages-add">Add to Menu</button></div></div>
      <div class="pc-menu-accordion"><button class="pc-acc" aria-expanded="false">Posts / Categories <span>⌄</span></button><div class="pc-acc-body"><div class="pc-source-tabs"><button class="active" data-source="posts">Posts</button><button data-source="categories">Categories</button></div><input id="pc-tax-search" type="search" placeholder="Search"><div id="pc-tax-list" class="pc-source-list"></div><button class="btn small" id="pc-tax-add">Add Selected</button></div></div>
      <div class="pc-menu-accordion"><button class="pc-acc" aria-expanded="false">Custom Link <span>⌄</span></button><div class="pc-acc-body"><label>URL<input id="pc-custom-url" placeholder="https://example.com"></label><label>Navigation Label<input id="pc-custom-label" placeholder="Label"></label><label><input id="pc-custom-blank" type="checkbox"> Open in new tab</label><label><input id="pc-custom-nofollow" type="checkbox"> rel="nofollow"</label><button class="btn small" id="pc-custom-add">Add Custom Link</button></div></div>
      <div class="pc-menu-quick"><button class="btn secondary" id="pc-create-page">＋ Create New Page</button><button class="btn secondary" id="pc-create-post">＋ Draft New Post</button></div>
    </aside>
    <section class="pc-menu-canvas"><div class="pc-menu-canvas-head"><div><strong id="pc-menu-name"><?=e($m['name'])?></strong><span class="pc-version">v<?=e((string)$m['version'])?></span></div><div><button class="btn secondary small" id="pc-menu-rollback">Undo Unsaved</button><button class="btn small" id="pc-menu-save">Save Menu</button></div></div><div id="pc-menu-tree" role="tree" aria-label="Menu structure"></div><div id="pc-menu-empty" class="pc-empty" hidden>No items yet. Add pages, posts, categories or a custom link.</div></section>
  </div>
  <div id="pc-menu-toast" class="pc-toast" role="status" aria-live="polite"></div>
</section>
<script>window.PocketCatMenuConfig={api:'/api/menu/',saveApi:'/api/menu/save/',csrf:'<?=e(csrf_token())?>',location:'<?=e($location)?>',menu:<?=json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};</script>
<script src="/assets/js/menu/manager.js" defer></script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
