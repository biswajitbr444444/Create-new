<?php
declare(strict_types=1);
/**
 * One-time/offline-runtime GeoLite2-City updater.
 * Runtime tracking never uses this script. Downloading is only for refreshing the local database.
 * Usage: MAXMIND_ACCOUNT_ID=... MAXMIND_LICENSE_KEY=... php geoip/update.php
 * Optional: GEOIP_FALLBACK_URL=https://.../GeoLite2-City.mmdb
 */
$root=dirname(__DIR__);$target=$root.'/geoip/GeoLite2-City.mmdb';$tmp=$root.'/geoip/.GeoLite2-City.download';
$account=trim((string)getenv('MAXMIND_ACCOUNT_ID'));$key=trim((string)getenv('MAXMIND_LICENSE_KEY'));$fallback=trim((string)getenv('GEOIP_FALLBACK_URL'));if($fallback==='')$fallback='https://cdn.jsdelivr.net/npm/geolite2-city@1.0.96/GeoLite2-City.mmdb.gz';
if($account!==''&&$key!==''){$url='https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz';$headers=['Authorization: Basic '.base64_encode($account.':'.$key),'User-Agent: PocketCat-GeoLite2-Updater/1.0'];}elseif($fallback!==''){$url=$fallback;$headers=['User-Agent: PocketCat-GeoLite2-Updater/1.0'];}else{fwrite(STDERR,"Missing credentials. Set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY, or GEOIP_FALLBACK_URL.\n");exit(2);}
$ch=curl_init($url);$fp=fopen($tmp,'wb');if(!$fp){fwrite(STDERR,"Cannot create temporary file.\n");exit(3);}curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>180,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FAILONERROR=>true]);$ok=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);fclose($fp);if(!$ok){@unlink($tmp);fwrite(STDERR,"GeoLite download failed (HTTP {$status}): {$err}\n");exit(4);}
$head=file_get_contents($tmp,false,null,0,8);if($head===false){@unlink($tmp);exit(5);} $installed=false;
if(substr($head,0,2)==="\x1f\x8b"){
    try{$phar=new PharData($tmp);$tar=$tmp.'.tar';if(!file_exists($tar))$phar->decompress();$tarPhar=new PharData($tar);foreach(new RecursiveIteratorIterator($tarPhar) as $file){$name=(string)$file->getFilename();if($name==='GeoLite2-City.mmdb'){copy((string)$file->getPathname(),$target);$installed=true;break;}}@unlink($tar);}catch(Throwable $e){$installed=false;}
}else{
    // Direct MMDB fallback.
    if(substr($head,0,4)!=="\x1f\x8b"){$installed=copy($tmp,$target);}
}
@unlink($tmp);if(!$installed||!is_file($target)||filesize($target)<1024){fwrite(STDERR,"Downloaded file did not contain a valid GeoLite2-City database.\n");exit(6);}echo "GeoLite2-City installed: ".$target." (".number_format(filesize($target)/1048576,1)." MB)\n";
