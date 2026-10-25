<?php
require __DIR__.'/../bootstrap.php'; require_admin(); ensure_cms_schema();
$id=(int)getv('id',0); $type=(($_GET['type']??'post')==='page')?'page':'post';
$s=db()->prepare("SELECT p.*,u.full_name author_name FROM cms_posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.id=? AND p.post_type=? LIMIT 1");$s->execute([$id,$type]);$item=$s->fetch();
if(!$item){http_response_code(404);exit('Content not found.');}
$title=$item['title']?:'(Untitled)'; public_header('Preview · '.$title);
?>
<section class="about-section cms-preview-page">
  <div class="page-head"><div><span class="eyebrow">ADMIN PREVIEW · <?=e(strtoupper($type))?></span><h1><?=e($title)?></h1><p class="muted">This preview is visible only to an authenticated administrator. Status: <strong><?=e(ucfirst($item['status']))?></strong>.</p></div><a class="btn secondary" href="/admin/<?=$type?>s/edit/?id=<?=$id?>">← Back to editor</a></div>
  <?php if(!empty($item['featured_image_url'])):?><img class="cms-featured-image" src="<?=e($item['featured_image_url'])?>" alt="<?=e($title)?>"><?php endif;?>
  <div class="cms-content"><?=$item['content']?></div>
</section>
<?php public_footer(); ?>