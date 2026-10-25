<?php
declare(strict_types=1);

/** PocketCat OTA updater. Updates are staged and validated before any live file is changed. */
function ota_root(): string {
    // Resolve from this source file, not DOCUMENT_ROOT: shared hosts may use
    // public_html, htdocs, a domain subdirectory, or a symlinked deployment.
    $candidate = realpath(dirname(__DIR__));
    if ($candidate === false || !is_dir($candidate)) throw new RuntimeException('Could not resolve the PocketCat application root from the installed source files.');
    return rtrim($candidate, DIRECTORY_SEPARATOR);
}
function ota_assert_safe_destination(string $relative): string {
    $relative = ota_safe_relative($relative);
    $root = ota_root();
    $cursor = $root;
    $parts = explode('/', $relative);
    foreach ($parts as $index => $part) {
        $cursor .= DIRECTORY_SEPARATOR.$part;
        if (is_link($cursor)) throw new RuntimeException('Refusing to deploy through a symbolic link: '.$relative);
        if ($index < count($parts)-1 && file_exists($cursor) && !is_dir($cursor)) throw new RuntimeException('A destination parent is not a directory: '.$relative);
    }
    $parent = dirname($cursor);
    $realParent = realpath($parent);
    if ($realParent !== false && $realParent !== $root && !str_starts_with($realParent, $root.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Destination resolves outside the application root: '.$relative);
    }
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function ota_storage_root(): string {
    $root = ota_root();
    if (is_link($root.'/storage') || is_link($root.'/storage/ota')) throw new RuntimeException('Private OTA storage cannot be placed through a symbolic link.');
    $base = $root.'/storage/ota';
    if (!is_dir($base) && !@mkdir($base, 0750, true) && !is_dir($base)) throw new RuntimeException('Cannot create private OTA storage directory. Check storage permissions.');
    @file_put_contents($base.'/.htaccess', "Require all denied\nDeny from all\n", LOCK_EX);
    @file_put_contents($base.'/index.html', '', LOCK_EX);
    return $base;
}
function ota_safe_relative(string $path): string {
    $path = str_replace('\\', '/', trim($path));
    while (str_starts_with($path, './')) $path = substr($path, 2);
    if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) || str_contains($path, "\0")) throw new RuntimeException('Archive contains an unsafe absolute or empty path.');
    $parts = explode('/', $path);
    foreach ($parts as $part) if ($part === '..' || $part === '.' || $part === '') throw new RuntimeException('Archive contains an unsafe path traversal entry: '.$path);
    return implode('/', $parts);
}
function ota_is_protected(string $rel): bool {
    $rel = strtolower(str_replace('\\','/',$rel));
    $base = basename($rel);
    if (in_array($base, ['.env','.env.local','.env.production','config.php','bootstrap.php','installed.lock','app-secret.php','app-config.php'], true)) return true;
    if (preg_match('~^(storage|uploads|upload|media/uploads|sessions|cache|vendor)/~', $rel)) return true;
    if (preg_match('~(^|/)(\.git|\.svn|\.well-known/private)(/|$)~', $rel)) return true;
    if (str_starts_with($rel, 'storage/')) return true;
    return false;
}
function ota_exec(array $args, ?string $cwd = null): array {
    if (!function_exists('proc_open')) throw new RuntimeException('This host disables proc_open; external archive formats cannot be processed here.');
    $spec = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p = @proc_open($args, $spec, $pipes, $cwd ?: ota_root(), ['PATH'=>getenv('PATH') ?: '/usr/bin:/bin']);
    if (!is_resource($p)) throw new RuntimeException('Could not start archive utility: '.basename((string)($args[0]??'command')));
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]); $code=proc_close($p);
    return [$code, (string)$out, (string)$err];
}
function ota_binary(string $name): ?string {
    foreach (explode(':', (string)(getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin')) as $dir) { $p=rtrim($dir,'/').'/'.$name; if (is_file($p) && is_executable($p)) return $p; }
    return null;
}
function ota_check_archive(string $file): array {
    $n=strtolower(basename($file));
    if (str_ends_with($n,'.zip')) {
        if (($bin=ota_binary('unzip'))!==null) { [$c,$o,$e]=ota_exec([$bin,'-tqq',$file]); if($c!==0) throw new RuntimeException('ZIP integrity check failed: '.trim($e.$o)); [$lc,$lo,$le]=ota_exec([$bin,'-Z1',$file]); if($lc!==0) throw new RuntimeException('Could not inspect ZIP entries: '.trim($le.$lo)); foreach(preg_split('/\r?\n/',$lo) as $entry) if(trim($entry)!=='' && trim($entry)!=='./') ota_safe_relative(rtrim($entry,'/')); $zi=ota_binary('zipinfo'); if($zi){[$zc,$zo,$ze]=ota_exec([$zi,'-l',$file]); if($zc!==0) throw new RuntimeException('Could not inspect ZIP file types.'); foreach(preg_split('/\r?\n/',$zo) as $line) if(preg_match('/^\s*[lbcps]/',$line)) throw new RuntimeException('ZIP contains a link or special file entry, which is not allowed in updates.');} elseif(class_exists('ZipArchive')) { $zcheck=new ZipArchive(); if($zcheck->open($file)!==true) throw new RuntimeException('Could not inspect ZIP metadata.'); for($zii=0;$zii<$zcheck->numFiles;$zii++){ $name=(string)$zcheck->getNameIndex($zii); if($name!=='' && $name!=='./') ota_safe_relative(rtrim($name,'/')); $opsys=0;$attr=0;if($zcheck->getExternalAttributesIndex($zii,$opsys,$attr) && $opsys===ZipArchive::OPSYS_UNIX && ((($attr>>16)&0170000)===0120000)) throw new RuntimeException('ZIP symlinks are not allowed in update packages.'); } $zcheck->close(); } else throw new RuntimeException('Cannot safely inspect ZIP link metadata on this host; enable PHP ZipArchive or install zipinfo.'); return ['format'=>'ZIP','extractor'=>'unzip']; }
        if (class_exists('ZipArchive')) { $z=new ZipArchive(); if($z->open($file)!==true) throw new RuntimeException('ZIP integrity check failed.'); for($i=0;$i<$z->numFiles;$i++) ota_safe_relative((string)$z->getNameIndex($i)); $z->close(); return ['format'=>'ZIP','extractor'=>'php-zip']; }
        throw new RuntimeException('ZIP support is unavailable: enable PHP ZipArchive or install the unzip utility.');
    }
    if (preg_match('/\.(tar\.gz|tgz|tar\.bz2|tbz2|tar\.xz|txz|tar)$/',$n)) {
        $bin=ota_binary('tar'); if(!$bin) throw new RuntimeException('TAR archive support is unavailable: install the tar utility.');
        [$c,$o,$e]=ota_exec([$bin,'-tf',$file]); if($c!==0) throw new RuntimeException('Archive integrity check failed: '.trim($e.$o)); foreach(preg_split('/\r?\n/',$o) as $entry) if(trim($entry)!=='' && trim($entry)!=='./') ota_safe_relative(rtrim($entry,'/')); [$tc,$to,$te]=ota_exec([$bin,'-tvf',$file]); if($tc!==0) throw new RuntimeException('Could not inspect TAR entry types.'); foreach(preg_split('/\r?\n/',$to) as $line) if(preg_match('/^[lh]/',$line)) throw new RuntimeException('TAR symlinks and hardlinks are not allowed in updates.');
        return ['format'=>strtoupper(preg_replace('/^.*\.(tar.*|tgz|tbz2|txz)$/','$1',$n)),'extractor'=>'tar'];
    }
    if (preg_match('/\.(gz|bz2|xz)$/',$n)) {
        $ext=pathinfo($n,PATHINFO_EXTENSION); $bin=ota_binary($ext==='gz'?'gzip':($ext==='bz2'?'bzip2':'xz'));
        if(!$bin) throw new RuntimeException(strtoupper($ext).' support is unavailable: install the matching decompressor.');
        $args= $ext==='gz'?[$bin,'-t',$file]:($ext==='bz2'?[$bin,'-t',$file]:[$bin,'-t',$file]); [$c,$o,$e]=ota_exec($args); if($c!==0) throw new RuntimeException('Compressed-file integrity check failed: '.trim($e.$o));
        return ['format'=>strtoupper($ext),'extractor'=>$ext];
    }
    if (str_ends_with($n,'.7z') || str_ends_with($n,'.rar')) {
        $bin=ota_binary('7z') ?: ota_binary('7zz'); if(!$bin && str_ends_with($n,'.rar')) $bin=ota_binary('unrar');
        if(!$bin) throw new RuntimeException('This archive format requires 7z/7zz (or unrar for RAR) to be installed on the server.');
        $args=basename($bin)==='unrar'?[$bin,'t',$file]:[$bin,'t','--',$file]; [$c,$o,$e]=ota_exec($args); if($c!==0) throw new RuntimeException('Archive integrity check failed: '.trim($e.$o));
        $list=basename($bin)==='unrar'?[$bin,'lb',$file]:[$bin,'l','-slt','--',$file]; [$lc,$lo,$le]=ota_exec($list); if($lc!==0) throw new RuntimeException('Could not inspect archive entries: '.trim($le.$lo)); if(preg_match('/\b(Symbolic Link|Hard Link|Symlink|Reparse Point)\b/i',$lo)) throw new RuntimeException('Links and reparse points are not allowed in update packages.');
        foreach(preg_split('/\r?\n/',$lo) as $line) { if(str_starts_with($line,'Path = ')){ $entry=substr($line,7); if($entry!==basename($file)) ota_safe_relative(rtrim($entry,'/')); } elseif(basename($bin)==='unrar' && trim($line)!=='') ota_safe_relative(rtrim(trim($line),'/')); }
        return ['format'=>str_ends_with($n,'.7z')?'7Z':'RAR','extractor'=>basename($bin)];
    }
    throw new RuntimeException('Unsupported archive extension. Supported: ZIP, TAR, TAR.GZ, TAR.BZ2, 7Z, RAR, TAR.XZ, GZ, BZ2 and XZ.');
}
function ota_decompress_bounded(string $binary, string $file, string $destination, int $limit): void {
    if (!function_exists('proc_open')) throw new RuntimeException('This host disables proc_open; standalone compressed files cannot be decompressed safely.');
    $pipes = [];
    $process = @proc_open([$binary, '-dc', $file], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, ota_root());
    if (!is_resource($process)) throw new RuntimeException('Could not start the decompression utility.');
    fclose($pipes[0]); stream_set_blocking($pipes[2], false);
    $out = @fopen($destination, 'xb');
    if (!$out) { proc_terminate($process); fclose($pipes[1]); fclose($pipes[2]); proc_close($process); throw new RuntimeException('Could not create the decompressed staging file.'); }
    $written = 0; $stderr = '';
    try {
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk === false) throw new RuntimeException('Could not read decompressor output.');
            if ($chunk === '') { $stderr .= (string)stream_get_contents($pipes[2]); continue; }
            $written += strlen($chunk);
            if ($written > $limit) { proc_terminate($process); throw new RuntimeException('Decompressed file exceeds the '.(int)($limit/1048576).' MB safety limit.'); }
            $offset = 0; $length = strlen($chunk);
            while ($offset < $length) { $n = fwrite($out, substr($chunk, $offset)); if ($n === false || $n === 0) throw new RuntimeException('Could not write decompressed staging data.'); $offset += $n; }
            $stderr .= (string)stream_get_contents($pipes[2]);
        }
        $stderr .= (string)stream_get_contents($pipes[2]);
    } catch (Throwable $e) { proc_terminate($process); throw $e; }
    finally { fclose($pipes[1]); fclose($pipes[2]); fclose($out); }
    $code = proc_close($process);
    if ($code !== 0) { @unlink($destination); throw new RuntimeException('Decompression failed: '.trim($stderr)); }
}
function ota_extract(string $file, array $info, string $stage): void {
    if (!mkdir($stage,0750,true) && !is_dir($stage)) throw new RuntimeException('Could not create staging directory.');
    $ext=strtolower(basename($file));
    if ($info['extractor']==='unzip') { [$c,$o,$e]=ota_exec([ota_binary('unzip'),'-qq',$file,'-d',$stage]); if($c!==0) throw new RuntimeException('ZIP extraction failed: '.trim($e.$o)); }
    elseif ($info['extractor']==='php-zip') { $z=new ZipArchive(); if($z->open($file)!==true) throw new RuntimeException('Could not open ZIP.'); for($i=0;$i<$z->numFiles;$i++){ $entry=(string)$z->getNameIndex($i); $rel=ota_safe_relative(rtrim($entry,'/')); if(str_ends_with($entry,'/')){ @mkdir($stage.'/'.$rel,0750,true); continue; } $parent=dirname($stage.'/'.$rel); if(!is_dir($parent)) mkdir($parent,0750,true); $in=$z->getStream($entry); $out=fopen($stage.'/'.$rel,'xb'); if(!$in||!$out) throw new RuntimeException('Could not extract '.$rel); stream_copy_to_stream($in,$out); fclose($in); fclose($out); } $z->close(); }
    elseif ($info['extractor']==='tar') { [$c,$o,$e]=ota_exec([ota_binary('tar'),'-xf',$file,'-C',$stage]); if($c!==0) throw new RuntimeException('TAR extraction failed: '.trim($e.$o)); }
    elseif (in_array($info['extractor'],['gz','bz2','xz'],true)) { $bin=ota_binary($info['extractor']==='gz'?'gzip':($info['extractor']==='bz2'?'bzip2':'xz')); $outName=preg_replace('/\.(gz|bz2|xz)$/i','',basename($file)); $outName=ota_safe_relative($outName); ota_decompress_bounded($bin,$file,$stage.'/'.$outName,100*1024*1024); }
    else { $bin=ota_binary('7z') ?: ota_binary('7zz'); if(!$bin && $info['extractor']==='unrar') $bin=ota_binary('unrar'); if(!$bin) throw new RuntimeException('Archive utility disappeared after validation.'); $args=basename($bin)==='unrar'?[$bin,'x','-o+',$file,$stage.'/']:[$bin,'x','-y','-o'.$stage,'--',$file]; [$c,$o,$e]=ota_exec($args); if($c!==0) throw new RuntimeException('Archive extraction failed: '.trim($e.$o)); }
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage,FilesystemIterator::SKIP_DOTS)); $count=0; $total=0;
    foreach($it as $f){ $path=$f->getPathname(); $rel=substr($path,strlen($stage)+1); ota_safe_relative($rel); if($f->isLink()) throw new RuntimeException('Symlinks are not allowed in update packages: '.$rel); if(!$f->isFile()) continue; $count++; $total+=$f->getSize(); if($count>10000) throw new RuntimeException('Update contains more than 10,000 files.'); if($total>250*1024*1024) throw new RuntimeException('Expanded update exceeds the 250 MB safety limit.'); }
}
function ota_flatten_stage(string $stage): string {
    // Accept common packages that wrap the project in a single top-level directory.
    $items=array_values(array_diff(scandir($stage)?:[],['.','..']));
    if(count($items)===1 && is_dir($stage.'/'.$items[0]) && !is_link($stage.'/'.$items[0])) { $inner=$stage.'/'.$items[0]; $move=$stage.'-flat'; if(!rename($inner,$move)) throw new RuntimeException('Could not normalize package root.'); @rmdir($stage); if(!rename($move,$stage)) throw new RuntimeException('Could not normalize package root.'); }
    return $stage;
}
function ota_collect_files(string $stage): array {
    $files=[]; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage,FilesystemIterator::SKIP_DOTS));
    foreach($it as $f){ if(!$f->isFile()) continue; $rel=ota_safe_relative(substr($f->getPathname(),strlen($stage)+1)); if(ota_is_protected($rel)) continue; if(str_starts_with(strtolower($rel),'migrations/')) continue; $files[$rel]=['path'=>$f->getPathname(),'size'=>$f->getSize(),'sha256'=>hash_file('sha256',$f->getPathname())]; }
    ksort($files); return $files;
}
function ota_validate_migrations(string $stage): array {
    $dir=$stage.'/migrations'; if(!is_dir($dir)) return []; $out=[];
    foreach(glob($dir.'/*.sql')?:[] as $file){ $sql=(string)file_get_contents($file); $clean=preg_replace('~/\*.*?\*/|--[^\r\n]*|#[^\r\n]*~s','',$sql); foreach(array_filter(array_map('trim',explode(';',(string)$clean))) as $stmt){ if(!preg_match('/^(CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS|ALTER\s+TABLE\s+[`\w]+\s+ADD\s+(?:COLUMN|INDEX|KEY)|CREATE\s+(?:UNIQUE\s+)?INDEX)\b/i',$stmt)) throw new RuntimeException('Migration rejected because only additive CREATE TABLE IF NOT EXISTS, ADD COLUMN/INDEX/KEY and CREATE INDEX statements are allowed: '.basename($file)); } $out[basename($file)]=$sql; }
    ksort($out); return $out;
}
function ota_apply_migrations(array $migrations): array {
    $done=[]; foreach($migrations as $name=>$sql){ foreach(array_filter(array_map('trim',explode(';',$sql))) as $stmt){ $clean=trim(preg_replace('~/\*.*?\*/|--[^\r\n]*|#[^\r\n]*~s','',$stmt)); if($clean==='')continue; if(!preg_match('/^(CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS|ALTER\s+TABLE\s+[`\w]+\s+ADD\s+(?:COLUMN|INDEX|KEY)|CREATE\s+(?:UNIQUE\s+)?INDEX)\b/i',$clean)) throw new RuntimeException('Unsafe migration statement in '.$name); db()->exec($clean); } $done[]=$name; } return $done;
}
function ota_php_lint(string $path): void {
    if(strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='php') return;
    $php=PHP_BINARY ?: ota_binary('php'); if(!$php) throw new RuntimeException('PHP CLI is unavailable, so PHP syntax validation cannot be performed.');
    [$c,$o,$e]=ota_exec([$php,'-l',$path]); if($c!==0) throw new RuntimeException('PHP syntax validation failed for '.basename($path).': '.trim($o.$e));
}
function ota_post_update_health_check(): void {
    $root=ota_root(); foreach(['index.php','bootstrap.php','config.php','includes/functions.php','includes/upload-engine.php'] as $required){$path=$root.'/'.$required;if(!is_file($path)||!is_readable($path))throw new RuntimeException('Post-update health check failed: required application file is missing or unreadable: '.$required);}
    if(function_exists('upload_prepare_directories')) upload_prepare_directories();
}
function ota_write_json(string $file,array $data): void { $tmp=$file.'.tmp'; file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX); @chmod($tmp,0600); if(!rename($tmp,$file)) throw new RuntimeException('Could not persist updater state.'); }
function ota_recover_interrupted(): array {
    $store=ota_storage_root(); $root=ota_root(); $recovered=[]; $dirs=glob($store.'/backups/*',GLOB_ONLYDIR)?:[]; rsort($dirs,SORT_STRING);
    foreach($dirs as $dir){ $mf=$dir.'/manifest.json'; if(!is_file($mf))continue; $m=json_decode((string)file_get_contents($mf),true); if(!is_array($m)||($m['status']??'')!=='applying')continue; $errs=[]; foreach(array_reverse($m['files']??[]) as $entry){ if(empty($entry['path']))continue; try{$rel=ota_safe_relative((string)$entry['path']);$target=ota_assert_safe_destination($rel);if(!empty($entry['existed'])&&!empty($entry['backup'])&&is_file($dir.'/'.$entry['backup'])){if(!@copy($dir.'/'.$entry['backup'],$target))$errs[]=$rel;}else{@unlink($target);}}catch(Throwable $e){$errs[]=(string)($entry['path']??'unknown');} } $m['status']=$errs?'recovery_failed':'rolled_back_after_interruption';$m['restore_errors']=$errs;$m['recovered_at']=gmdate('c');ota_write_json($mf,$m);$recovered[]=['id'=>$m['id']??basename($dir),'errors'=>$errs]; } return $recovered;
}
function ota_apply_stage(string $stage,string $id): array {
    $root=ota_root(); $store=ota_storage_root(); $files=ota_collect_files($stage); $migrations=ota_validate_migrations($stage);
    if(!$files) throw new RuntimeException('The package has no eligible application files after protected paths were excluded.');
    $lock = @fopen($store.'/update.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) { if (is_resource($lock)) fclose($lock); throw new RuntimeException('Another OTA deployment is already running. Wait for it to finish before applying another package.'); }
    @chmod($store.'/update.lock', 0600);
    $backup=$store.'/backups/'.$id; if(!is_dir($backup) && !mkdir($backup,0750,true)) throw new RuntimeException('Could not create rollback snapshot.');
    $manifest=['id'=>$id,'created_at'=>gmdate('c'),'status'=>'applying','files'=>[],'migrations'=>[]]; ota_write_json($backup.'/manifest.json',$manifest);
    $changed=[];
    try {
        foreach($files as $rel=>$item){ $target=ota_assert_safe_destination($rel); $parent=dirname($target); if(!is_dir($parent) && !mkdir($parent,0755,true) && !is_dir($parent)) throw new RuntimeException('Cannot create destination directory for '.$rel); if(is_link($target)) throw new RuntimeException('Refusing to overwrite symlink target: '.$rel); if(file_exists($target) && !is_file($target)) throw new RuntimeException('Destination is not a regular file: '.$rel);
            if(is_file($target) && hash_file('sha256',$target)===$item['sha256']) continue;
            $entry=['path'=>$rel,'existed'=>is_file($target),'backup'=>null,'new_sha256'=>$item['sha256']];
            if(is_file($target)){ $b=$backup.'/files/'.$rel; if(!is_dir(dirname($b))) mkdir(dirname($b),0750,true); if(!copy($target,$b)) throw new RuntimeException('Could not back up affected file: '.$rel); $entry['backup']='files/'.$rel; }
            $manifest['files'][]=$entry; ota_write_json($backup.'/manifest.json',$manifest);
            $tmp=$target.'.ota-'.$id.'.tmp'; if(!copy($item['path'],$tmp)) throw new RuntimeException('Could not stage live file: '.$rel); @chmod($tmp,is_file($target)?(fileperms($target)&0777):0644); if(!rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('Could not atomically replace file: '.$rel);} $changed[]=$rel;
            ota_php_lint($target);
        }
        ota_post_update_health_check();
        $manifest['migrations']=ota_apply_migrations($migrations);
        ota_post_update_health_check();
        $manifest['status']='applied'; $manifest['completed_at']=gmdate('c'); ota_write_json($backup.'/manifest.json',$manifest);
        // Keep the installed release marker aligned with this updater release, not a stale historical version.
        $versionPath = $root.'/VERSION';
        if (is_file($versionPath) && is_writable(dirname($versionPath))) @file_put_contents($versionPath, "19.2\n", LOCK_EX);
        return ['id'=>$id,'changed'=>$changed,'migrations'=>$manifest['migrations'],'backup'=>$backup];
    } catch(Throwable $e) {
        $restoreErrors=[]; foreach(array_reverse($manifest['files']) as $entry){$target=$root.'/'.$entry['path']; if($entry['existed'] && $entry['backup'] && is_file($backup.'/'.$entry['backup'])){if(!@copy($backup.'/'.$entry['backup'],$target))$restoreErrors[]=$entry['path'];}else{@unlink($target);}}
        $manifest['status']='rolled_back'; $manifest['error']=$e->getMessage(); $manifest['restore_errors']=$restoreErrors; $manifest['failed_at']=gmdate('c'); ota_write_json($backup.'/manifest.json',$manifest);
        if($restoreErrors) throw new RuntimeException($e->getMessage().' Automatic rollback had failures for: '.implode(', ',$restoreErrors),0,$e);
        throw new RuntimeException($e->getMessage().' All changed files were rolled back. Database migrations are restricted to additive operations; any already-applied additive schema changes are retained.',0,$e);
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}
