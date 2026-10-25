<?php
declare(strict_types=1);
function qr_schema():void{
    try{db()->exec("CREATE TABLE IF NOT EXISTS qr_designs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,link_id BIGINT UNSIGNED NOT NULL UNIQUE,design_json LONGTEXT NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(link_id),CONSTRAINT fk_qr_design_link FOREIGN KEY(link_id) REFERENCES short_links(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable $e){}
}
function qr_default_design():array{
    return ['version'=>2,'fg'=>'#111827','bg'=>'#ffffff','size'=>'auto','margin'=>4,'logo_url'=>'','foreground_image'=>'','background_image'=>'','foreground_opacity'=>1,'background_opacity'=>0.18,'foreground_x'=>50,'foreground_y'=>50,'foreground_scale'=>0.22,'background_x'=>50,'background_y'=>50,'background_scale'=>1,'foreground_mode'=>'center','background_mode'=>'cover'];
}
function qr_design_get(int $linkId):array{
    $d=qr_default_design();
    try{$q=db()->prepare('SELECT design_json FROM qr_designs WHERE link_id=? LIMIT 1');$q->execute([$linkId]);$raw=$q->fetchColumn();if($raw){$x=json_decode((string)$raw,true);if(is_array($x))$d=array_replace($d,$x);}}catch(Throwable $e){}
    return $d;
}
function qr_design_save(int $linkId,array $design):void{
    $allowed=qr_default_design();$out=$allowed;
    foreach(array_keys($allowed) as $k)if(array_key_exists($k,$design))$out[$k]=$design[$k];
    $out['fg']=preg_match('/^#[0-9a-fA-F]{6}$/',(string)$out['fg'])?$out['fg']:'#111827';
    $out['bg']=preg_match('/^#[0-9a-fA-F]{6}$/',(string)$out['bg'])?$out['bg']:'#ffffff';
    $out['size']=in_array((string)$out['size'],['auto','512','768','1024','1536','2048'],true)?(string)$out['size']:'auto';
    $out['margin']=max(0,min(32,(int)$out['margin']));
    foreach(['foreground_opacity','background_opacity'] as $k)$out[$k]=max(0,min(1,(float)$out[$k]));
    foreach(['foreground_x','foreground_y','background_x','background_y'] as $k)$out[$k]=max(0,min(100,(float)$out[$k]));
    foreach(['foreground_scale','background_scale'] as $k)$out[$k]=max(.05,min(3,(float)$out[$k]));
    $out['logo_url']=qr_safe_image_url((string)$out['logo_url']);
    $out['foreground_image']=qr_safe_image_url((string)$out['foreground_image']);
    $out['background_image']=qr_safe_image_url((string)$out['background_image']);
    $pdo=db();$pdo->prepare('INSERT INTO qr_designs(link_id,design_json,created_at,updated_at) VALUES(?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE design_json=VALUES(design_json),updated_at=NOW()')->execute([$linkId,json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}
function qr_safe_image_url(string $url):string{
    $url=trim($url); if($url==='')return '';
    if(str_starts_with($url,'/uploads/qr/'))return preg_match('~^/uploads/qr/[A-Za-z0-9._-]+$~',$url)?$url:'';
    if(preg_match('~^https://~i',$url))return $url;
    return '';
}
function qr_upload_image(string $field,int $userId):string{
    if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return '';
    $f=$_FILES[$field];if((int)$f['size']>3*1024*1024)throw new RuntimeException('QR image is too large. Maximum size is 3 MB.');
    $tmp=(string)$f['tmp_name'];$info=@getimagesize($tmp);if(!$info)throw new RuntimeException('QR image is not a valid image.');
    $mime=(string)($info['mime']??'');$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'][$mime]??'';if(!$ext)throw new RuntimeException('Allowed QR images: PNG, JPG, WEBP or GIF.');
    $dir=__DIR__.'/../uploads/qr';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('QR upload directory is unavailable.');
    $name='u'.(int)$userId.'_'.bin2hex(random_bytes(8)).'.'.$ext;$path=$dir.'/'.$name;if(!move_uploaded_file($tmp,$path))throw new RuntimeException('Could not save QR image.');
    @chmod($path,0644);return '/uploads/qr/'.$name;
}
