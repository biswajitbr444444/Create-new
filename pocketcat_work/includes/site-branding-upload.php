<?php
function site_logo_local_path(string $url): ?string {
 $prefix='/uploads/site-branding/';
 if(strpos($url,$prefix)!==0) return null;
 $name=basename(parse_url($url,PHP_URL_PATH) ?: '');
 if($name==='' || !preg_match('/^[a-f0-9]{24}\.(png|jpe?g|webp|svg)$/i',$name)) return null;
 return dirname(__DIR__).'/uploads/site-branding/'.$name;
}
function save_uploaded_site_logo(array $file): string {
 if(!isset($file['error']) || is_array($file['error'])) throw new RuntimeException('The logo upload could not be processed.');
 if((int)$file['error']===UPLOAD_ERR_NO_FILE) return '';
 if((int)$file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Logo upload failed. Please choose the file again.');
 if((int)($file['size']??0)>5*1024*1024) throw new RuntimeException('The logo must be 5 MB or smaller.');
 $tmp=(string)($file['tmp_name']??'');
 if($tmp==='' || !is_uploaded_file($tmp)) throw new RuntimeException('The uploaded logo file is invalid.');
 $original=strtolower((string)($file['name']??''));
 $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
 $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($tmp);
 $allowed=['png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'webp'=>['image/webp'],'svg'=>['image/svg+xml','text/plain','text/xml','application/xml']];
 if(!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) throw new RuntimeException('Use a valid PNG, JPG, SVG, or WebP image.');
 $bytes=file_get_contents($tmp);
 if($bytes===false || $bytes==='') throw new RuntimeException('The uploaded logo file is empty.');
 if($ext==='svg') {
  if(strlen($bytes)>2*1024*1024) throw new RuntimeException('SVG logos must be 2 MB or smaller.');
  if(!preg_match('/<svg\b/i',$bytes) || preg_match('/<!DOCTYPE|<!ENTITY|<\s*(script|foreignObject|iframe|object|embed)\b|\bon[a-z]+\s*=|javascript\s*:|data\s*:\s*text\/html/i',$bytes)) throw new RuntimeException('This SVG contains unsupported or unsafe markup. Please export a clean SVG logo.');
 }
 $dir=dirname(__DIR__).'/uploads/site-branding';
 if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Could not create the logo upload directory.');
 if(!is_writable($dir)) throw new RuntimeException('The logo upload directory is not writable by the application.');
 $name=bin2hex(random_bytes(12)).'.'.($ext==='jpeg'?'jpg':$ext);
 $target=$dir.'/'.$name;
 if($ext==='svg') { if(file_put_contents($target,$bytes,LOCK_EX)===false) throw new RuntimeException('Could not save the SVG logo.'); }
 elseif(!move_uploaded_file($tmp,$target)) throw new RuntimeException('Could not save the uploaded logo.');
 if(!is_file($target) || !is_readable($target) || (int)filesize($target)!==strlen($bytes)) {
  if(is_file($target)) @unlink($target);
  throw new RuntimeException('The uploaded logo could not be read after saving.');
 }
 if(!@chmod($target,0644) && (((int)@fileperms($target)&0777)!==0644)) {
  @unlink($target);
  throw new RuntimeException('Could not set safe permissions on the uploaded logo.');
 }
 return '/uploads/site-branding/'.$name;
}
