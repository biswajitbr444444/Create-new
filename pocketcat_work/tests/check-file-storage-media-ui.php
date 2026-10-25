<?php
$root = dirname(__DIR__);
$filesPage = file_get_contents($root.'/files/index.php');
$mediaPage = file_get_contents($root.'/media/index.php');
$fileCss = file_get_contents($root.'/assets/css/file-sharing-preferences-v18.9.css');
$checks = [
    'File Storage upload description has a desktop text column with bounded controls' =>
        strpos($fileCss, '.upload-panel .modern-drop{grid-template-columns:52px minmax(0,1fr) minmax(180px,220px)') !== false
        && strpos($fileCss, '@media(max-width:520px)') !== false,
    'File Storage preference list mode uses a single readable card column' =>
        strpos($fileCss, '#fileGrid.pc-list-view>.file-card{display:flex;flex-direction:column') !== false
        && strpos($fileCss, '#fileGrid .file-card-top{display:grid;grid-template-columns:auto 40px minmax(0,1fr) auto') !== false,
    'Media uploads retry stalled chunks automatically without user pause/resume' =>
        strpos($mediaPage, 'setTimeout(()=>{if(!settled)xhr.abort()},20000)') !== false
        && strpos($mediaPage, 'xhr.upload.onload=clearStall') !== false
        && strpos($mediaPage, 'retryableRequest(async()=>{try{return await xhrChunk') !== false,
    'Media upload requests have a finite timeout and visible startup status' =>
        strpos($mediaPage, 'controller.abort(),30000') !== false
        && strpos($mediaPage, "setStatus(s,'Starting upload") !== false,
    'Updated file workspace CSS is cache-busted on both file and media pages' =>
        strpos($filesPage, 'file-sharing-preferences-v18.9.css?v=19.79-file-layout') !== false
        && strpos($mediaPage, 'file-sharing-preferences-v18.9.css?v=19.79-file-layout') !== false,
];
$failed = false;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ').$label."\n";
    if (!$ok) $failed = true;
}
if ($failed) exit(1);
echo "All file storage and media UI checks passed.\n";
