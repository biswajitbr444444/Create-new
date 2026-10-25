<?php
require __DIR__.'/../bootstrap.php';
require_login();
$u=current_user();
$slug=preg_replace('/[^A-Za-z0-9_-]/','',(string)($_GET['slug']??''));
$q=db()->prepare('SELECT * FROM short_links WHERE slug=? AND user_id=?');$q->execute([$slug,(int)$u['id']]);$l=$q->fetch();
if(!$l){http_response_code(404);exit('Link not found.');}
$url=link_public_url($l['slug']);$design=qr_design_get((int)$l['id']);
require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">QR SUITE</span><h1>QR code editor</h1><p class="qr-url-head"><?=e($url)?></p></div></div>
<div class="qr-editor-layout">
<section class="card qr-controls-card">
  <div class="qr-section-head"><div><h2>Design</h2><p class="muted">Changes appear instantly in the live preview.</p></div><span class="badge" id="qrSaveState">Saved</span></div>
  <div class="qr-control-grid">
    <label>Foreground <input class="qr-color-input" type="color" id="qrFg" value="<?=e($design['fg'])?>"><span class="qr-color-value" id="qrFgValue"><?=e($design['fg'])?></span></label>
    <label>Background <input class="qr-color-input" type="color" id="qrBg" value="<?=e($design['bg'])?>"><span class="qr-color-value" id="qrBgValue"><?=e($design['bg'])?></span></label>
    <label>Resolution<select id="qrSize"><option value="auto" <?=($design['size']==='auto'?'selected':'')?>>Auto</option><?php foreach(['512','768','1024','1536','2048'] as $sz):?><option value="<?=$sz?>" <?=((string)$design['size']===$sz?'selected':'')?>><?=$sz?> px</option><?php endforeach;?></select></label>
    <label>Quiet zone / margin<input id="qrMargin" type="number" min="0" max="32" value="<?=e($design['margin'])?>"></label>
  </div>
  <div class="qr-divider"></div>
  <h3>Logo</h3>
  <div class="qr-control-grid">
    <label>Center logo URL<input id="qrLogo" type="url" placeholder="https://…" value="<?=e($design['logo_url'])?>"></label>
    <label>Logo / foreground image<input id="qrForegroundFile" type="file" accept="image/png,image/jpeg,image/webp,image/gif"><small class="muted">PNG/JPG/WEBP/GIF, max 3 MB.</small></label>
  </div>
  <div class="qr-slider-grid">
    <label>Foreground X <input id="qrFgX" type="range" min="0" max="100" step="1" value="<?=e($design['foreground_x'])?>"><output id="qrFgXOut"><?=e($design['foreground_x'])?>%</output></label>
    <label>Foreground Y <input id="qrFgY" type="range" min="0" max="100" step="1" value="<?=e($design['foreground_y'])?>"><output id="qrFgYOut"><?=e($design['foreground_y'])?>%</output></label>
    <label>Foreground Scale <input id="qrFgScale" type="range" min="0.05" max="0.6" step="0.01" value="<?=e($design['foreground_scale'])?>"><output id="qrFgScaleOut"><?=round((float)$design['foreground_scale']*100)?>%</output></label>
    <label>Foreground Opacity <input id="qrFgOpacity" type="range" min="0" max="1" step="0.05" value="<?=e($design['foreground_opacity'])?>"><output id="qrFgOpacityOut"><?=round((float)$design['foreground_opacity']*100)?>%</output></label>
  </div>
  <div class="qr-divider"></div>
  <h3>Background image</h3>
  <div class="qr-control-grid">
    <label>Upload background image<input id="qrBackgroundFile" type="file" accept="image/png,image/jpeg,image/webp,image/gif"><small class="muted">The QR remains above the image for readability.</small></label>
    <label>Background image URL<input id="qrBackgroundUrl" type="url" placeholder="https://…" value="<?=e($design['background_image'])?>"></label>
  </div>
  <div class="qr-slider-grid">
    <label>Background X <input id="qrBgX" type="range" min="0" max="100" step="1" value="<?=e($design['background_x'])?>"><output id="qrBgXOut"><?=e($design['background_x'])?>%</output></label>
    <label>Background Y <input id="qrBgY" type="range" min="0" max="100" step="1" value="<?=e($design['background_y'])?>"><output id="qrBgYOut"><?=e($design['background_y'])?>%</output></label>
    <label>Background Scale <input id="qrBgScale" type="range" min="0.5" max="2.5" step="0.05" value="<?=e($design['background_scale'])?>"><output id="qrBgScaleOut"><?=round((float)$design['background_scale']*100)?>%</output></label>
    <label>Background Opacity <input id="qrBgOpacity" type="range" min="0" max="1" step="0.05" value="<?=e($design['background_opacity'])?>"><output id="qrBgOpacityOut"><?=round((float)$design['background_opacity']*100)?>%</output></label>
  </div>
  <div class="qr-action-row">
    <button class="btn" type="button" id="qrGenerate">Generate</button>
    <button class="btn secondary" type="button" id="qrSave">Save design</button>
    <button class="btn secondary" type="button" id="qrReset">Reset design</button>
  </div>
  <div class="qr-share-row" aria-label="Share QR">
    <button class="btn small secondary" type="button" id="qrShareNative">Share</button>
    <a class="btn small secondary" target="_blank" rel="noopener" id="qrShareWhatsApp">WhatsApp</a>
    <a class="btn small secondary" target="_blank" rel="noopener" id="qrShareTelegram">Telegram</a>
    <a class="btn small secondary" target="_blank" rel="noopener" id="qrShareX">X</a>
    <a class="btn small secondary" target="_blank" rel="noopener" id="qrShareFacebook">Facebook</a>
  </div>
</section>
<section class="card qr-preview-card">
  <div class="qr-preview-head"><div><h2>Live preview</h2><p class="muted">Auto sizing adapts to the available screen width.</p></div><span class="qr-size-pill" id="qrActualSize">Auto</span></div>
  <div class="qr-preview-stage"><div id="qrBox" class="qr-box" aria-live="polite"></div></div>
  <div class="qr-download-row"><button class="btn secondary" type="button" id="qrPng">Download PNG</button><button class="btn secondary" type="button" id="qrSvg">Download SVG</button></div>
  <p class="muted qr-readability-note">Tip: keep strong contrast and avoid covering too much of the QR modules with the foreground image.</p>
</section>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>window.POCKETCAT_QR=<?=json_encode(['link_id'=>(int)$l['id'],'slug'=>$l['slug'],'url'=>$url,'design'=>$design,'created'=>isset($_GET['created'])||isset($_GET['edit'])],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;</script>
<script src="/assets/js/qr-editor.js" defer></script>
<?php require __DIR__.'/../includes/footer.php'; ?>
