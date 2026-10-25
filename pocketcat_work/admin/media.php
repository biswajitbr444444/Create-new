<?php
require __DIR__.'/../bootstrap.php';
require_login();
$cu=current_user();
$db=db();
$isAdmin=($cu['role']??'')==='admin';$uid=(int)$cu['id'];
$msg='';$msgType='info';

function media_kind(string $mime,string $name):string{
    $m=strtolower($mime);$n=strtolower($name);
    if(str_starts_with($m,'image/'))return'image';
    if(str_starts_with($m,'audio/'))return'audio';
    if(str_starts_with($m,'video/'))return'video';
    if($m==='application/zip'||str_ends_with($n,'.zip'))return'archive';
    return'file';
}
function media_clean_folder(string $v):string{
    $v=trim($v);$v=preg_replace('/[^A-Za-z0-9._ -]+/','-',substr($v,0,120));
    return trim((string)$v)?:'General';
}
function media_clean_name(string $v):string{return trim(mb_substr(basename($v),0,180));}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $a=(string)post('action');
    try{
        if($a==='create_folder' && $isAdmin){
            $folder=media_clean_folder((string)post('folder'));
            $exists=$db->prepare('SELECT COUNT(*) FROM media_assets WHERE folder_name=?');$exists->execute([$folder]);
            if((int)$exists->fetchColumn()>0){$msg='That folder already exists or contains media.';$msgType='error';}
            else{
                $db->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_url,mime_type,size_bytes,kind,auto_fit,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())')
                   ->execute([$uid,'.folder-marker',$folder,'url','about:blank','application/x-folder-marker',0,'file',1]);
                $msg='Folder created: '.$folder;
            }
        } elseif($a==='rename_folder' && $isAdmin){
            $old=media_clean_folder((string)post('old_folder'));$new=media_clean_folder((string)post('new_folder'));
            if($old===$new){$msg='No folder name change was made.';}
            else{
                $q=$db->prepare('UPDATE media_assets SET folder_name=?,updated_at=NOW() WHERE folder_name=?');$q->execute([$new,$old]);
                $msg='Folder renamed. '.$q->rowCount().' asset(s) updated.';
            }
        } elseif($a==='delete_folder' && $isAdmin){
            $folder=media_clean_folder((string)post('folder'));
            $q=$db->prepare('SELECT COUNT(*) FROM media_assets WHERE folder_name=? AND name<>?');$q->execute([$folder,'.folder-marker']);
            if((int)$q->fetchColumn()>0){$msg='Folder is not empty. Move or delete its assets first.';$msgType='error';}
            else{$db->prepare('DELETE FROM media_assets WHERE folder_name=? AND name=?')->execute([$folder,'.folder-marker']);$msg='Folder deleted: '.$folder;}
        } elseif($a==='upload' && !empty($_FILES['media']['tmp_name'])){
            $fs=$_FILES['media'];
            $tmp=is_array($fs['tmp_name'])?$fs['tmp_name']:[$fs['tmp_name']];
            $names=is_array($fs['name'])?$fs['name']:[$fs['name']];
            $sizes=is_array($fs['size'])?$fs['size']:[$fs['size']];
            $errors=is_array($fs['error'])?$fs['error']:[$fs['error']];
            $folder=media_clean_folder((string)post('folder','General'));
            $dir=__DIR__.'/../uploads/media/'.$uid;if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Media upload directory could not be created.');
            $ok=0;$skipped=0;
            foreach($tmp as $i=>$tp){
                if(($errors[$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($tp)){$skipped++;continue;}
                $size=(int)($sizes[$i]??0);
                if($size<=0||$size>max(file_local_max_bytes(),5*1024*1024*1024)){$skipped++;continue;}
                $name=media_clean_name((string)($names[$i]??''));if($name===''){$skipped++;continue;}
                $mime=function_exists('mime_content_type')?(mime_content_type($tp)?:'application/octet-stream'):(string)($fs['type'][$i]??'application/octet-stream');
                $kind=media_kind($mime,$name);$ext=pathinfo($name,PATHINFO_EXTENSION);
                $safeExt=$ext?'.'.preg_replace('/[^a-zA-Z0-9]+/','',$ext):'';$file=bin2hex(random_bytes(12)).$safeExt;$path=$dir.'/'.$file;
                if(!@move_uploaded_file($tp,$path) && !@copy($tp,$path)){$skipped++;continue;}
                $url='/uploads/media/'.$uid.'/'.$file;$w=null;$h=null;
                if($kind==='image'){$info=@getimagesize($path);$w=$info[0]??null;$h=$info[1]??null;}
                $db->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_url,storage_path,mime_type,size_bytes,kind,width,height,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
                   ->execute([$uid,$name,$folder,'upload',$url,$url,$mime,$size,$kind,$w,$h,1]);$ok++;
            }
            $msg=$ok?'Uploaded '.$ok.' asset(s).':'No files were uploaded.';if($skipped)$msg.=' '.$skipped.' file(s) were skipped (invalid upload or over the current PHP upload limit).';
        } elseif($a==='url'){
            $url=trim((string)post('source_url'));$name=media_clean_name((string)post('name','Remote media'));$folder=media_clean_folder((string)post('folder','General'));$mime=trim((string)post('mime_type','application/octet-stream'));
            if(!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$url)){$msg='Only valid HTTP/HTTPS public URLs are allowed.';$msgType='error';}
            elseif($name===''){$msg='A media name is required.';$msgType='error';}
            else{$db->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_url,mime_type,size_bytes,kind,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$uid,$name,$folder,'url',$url,$mime,0,media_kind($mime,$name),1]);$msg='Public media URL saved.';}
        } elseif($a==='edit'){
            $id=(int)post('id');$name=media_clean_name((string)post('name'));$folder=media_clean_folder((string)post('folder'));$alt=trim((string)post('alt_text'));$desc=trim((string)post('description'));$fav=!empty($_POST['is_favorite'])?1:0;
            if($id>0&&$name!==''){$db->prepare('UPDATE media_assets SET name=?,folder_name=?,alt_text=?,description=?,is_favorite=?,updated_at=NOW() WHERE id=?')->execute([$name,$folder,mb_substr($alt,0,500),mb_substr($desc,0,2000),$fav,$id]);$msg='Media details saved.';}
            else{$msg='Could not save media details.';$msgType='error';}
        } elseif($a==='favorite'){
            $id=(int)post('id');$db->prepare('UPDATE media_assets SET is_favorite=1-is_favorite,updated_at=NOW() WHERE id=?')->execute([$id]);$msg='Favorite status updated.';
        } elseif($a==='bulk_move'){
            $ids=array_values(array_filter(array_map('intval',(array)post('ids',[]))));$folder=media_clean_folder((string)post('target_folder','General'));
            if(!$ids){$msg='Select at least one media item.';$msgType='error';}
            else{$ph=implode(',',array_fill(0,count($ids),'?'));$db->prepare("UPDATE media_assets SET folder_name=?,updated_at=NOW() WHERE id IN ($ph)")->execute(array_merge([$folder],$ids));$msg='Moved '.$db->rowCount().' asset(s) to '.$folder.'.';}
        } elseif($a==='bulk_delete' && $isAdmin){
            $ids=array_values(array_filter(array_map('intval',(array)post('ids',[]))));
            if(!$ids){$msg='Select at least one media item.';$msgType='error';}
            else{
                $ph=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT storage_path FROM media_assets WHERE id IN ($ph)");$q->execute($ids);
                foreach($q->fetchAll() as $m){if(!empty($m['storage_path'])&&str_starts_with((string)$m['storage_path'],'/uploads/'))@unlink(__DIR__.'/..'.$m['storage_path']);}
                $db->prepare("DELETE FROM media_assets WHERE id IN ($ph)")->execute($ids);$msg='Deleted '.$db->rowCount().' asset(s).';
            }
        } elseif($a==='delete' && $isAdmin){
            $id=(int)post('id');$q=$db->prepare('SELECT storage_path FROM media_assets WHERE id=?');$q->execute([$id]);$m=$q->fetch();
            if($m&&$m['storage_path']&&str_starts_with((string)$m['storage_path'],'/uploads/'))@unlink(__DIR__.'/..'.$m['storage_path']);
            $db->prepare('DELETE FROM media_assets WHERE id=?')->execute([$id]);$msg='Media deleted.';
        }
    }catch(Throwable $e){$msg='Media operation failed safely. Check storage permissions or database schema.';$msgType='error';}
}

$folder=(string)getv('folder','');$q=trim((string)getv('q',''));$kind=(string)getv('kind','');$date=(string)getv('date','');$sort=(string)getv('sort','newest');$view=(string)getv('view','grid');
$allowedDates=['','today','7d','30d','90d','year'];if(!in_array($date,$allowedDates,true))$date='';
$allowedKinds=['image','video','audio','archive','file'];if(!in_array($kind,$allowedKinds,true))$kind='';
$sortSql=['newest'=>'created_at DESC','oldest'=>'created_at ASC','name'=>'name ASC','largest'=>'size_bytes DESC','favorites'=>'is_favorite DESC, created_at DESC'][$sort]??'created_at DESC';
$sql='SELECT * FROM media_assets WHERE name<>?';$params=['.folder-marker'];
if($folder!==''){$sql.=' AND folder_name=?';$params[]=$folder;}
if($kind!==''){$sql.=' AND kind=?';$params[]=$kind;}
if($q!==''){$sql.=' AND (name LIKE ? OR alt_text LIKE ? OR description LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
if($date!==''){$days=['today'=>0,'7d'=>7,'30d'=>30,'90d'=>90,'year'=>365][$date];$sql.=$days===0?' AND created_at>=CURDATE()':' AND created_at>=DATE_SUB(NOW(),INTERVAL '.$days.' DAY)';}
$sql.=' ORDER BY '.$sortSql.' LIMIT 500';$st=$db->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
$folders=$db->query("SELECT folder_name,COUNT(*) n FROM media_assets WHERE name<>'.folder-marker' GROUP BY folder_name ORDER BY folder_name")->fetchAll();
$stats=['total'=>0,'image'=>0,'video'=>0,'audio'=>0,'file'=>0,'favorite'=>0];$sq=$db->query("SELECT kind,COUNT(*) n,SUM(is_favorite) fav FROM media_assets WHERE name<>'.folder-marker' GROUP BY kind");foreach($sq as $r){$stats['total']+=(int)$r['n'];$k2=$r['kind'];if(isset($stats[$k2]))$stats[$k2]+=(int)$r['n'];$stats['favorite']+=(int)$r['fav'];}
$title='Media Library';require __DIR__.'/../includes/header.php';
?>
<div class="page-head media-library-head"><div><span class="eyebrow">MEDIA PLATFORM</span><h1>Media Library</h1><p>Organize, preview, search and reuse your media from one responsive workspace.</p></div><a class="btn" href="#media-upload">Add media</a></div>
<?php if($msg):?><div class="flash <?=e($msgType)?>"><?=e($msg)?></div><?php endif;?>
<section class="media-stats"><div class="card"><b><?=number_format($stats['total'])?></b><span>Total assets</span></div><div class="card"><b><?=number_format($stats['image'])?></b><span>Images</span></div><div class="card"><b><?=number_format($stats['video']+$stats['audio'])?></b><span>Audio + video</span></div><div class="card"><b><?=number_format($stats['favorite'])?></b><span>Favorites</span></div></section>
<section class="card media-toolbar"><form method="get" class="media-filter-form"><label>Search<input name="q" value="<?=e($q)?>" placeholder="Filename, alt text, description"></label><label>Type<select name="kind"><option value="">All types</option><?php foreach($allowedKinds as $k):?><option value="<?=$k?>" <?=$kind===$k?'selected':''?>><?=e(ucfirst($k))?></option><?php endforeach;?></select></label><label>Folder<select name="folder"><option value="">All folders</option><?php foreach($folders as $f):?><option value="<?=e($f['folder_name'])?>" <?=$folder===$f['folder_name']?'selected':''?>><?=e($f['folder_name'])?> (<?=$f['n']?>)</option><?php endforeach;?></select></label><label>Date<select name="date"><option value="">Any date</option><option value="today" <?=$date==='today'?'selected':''?>>Today</option><option value="7d" <?=$date==='7d'?'selected':''?>>Last 7 days</option><option value="30d" <?=$date==='30d'?'selected':''?>>Last 30 days</option><option value="90d" <?=$date==='90d'?'selected':''?>>Last 90 days</option><option value="year" <?=$date==='year'?'selected':''?>>Last year</option></select></label><label>Sort<select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Oldest</option><option value="name" <?=$sort==='name'?'selected':''?>>Name</option><option value="largest" <?=$sort==='largest'?'selected':''?>>Largest</option><option value="favorites" <?=$sort==='favorites'?'selected':''?>>Favorites</option></select></label><input type="hidden" name="view" value="<?=e($view)?>"><button class="btn">Filter</button><a class="btn secondary" href="/admin/media/">Reset</a></form>
<div class="media-folder-bar"><a class="btn small <?=$folder===''?'':'secondary'?>" href="/admin/media/">All</a><?php foreach($folders as $f):?><a class="btn small <?=$folder===$f['folder_name']?'':'secondary'?>" href="/admin/media/?folder=<?=rawurlencode($f['folder_name'])?>"><?=e($f['folder_name'])?> <span class="muted">(<?=$f['n']?>)</span></a><?php endforeach;?></div>
<div class="media-view-toggle"><span class="muted"><?=count($rows)?> shown</span><a class="btn small <?=$view==='grid'?'':'secondary'?>" href="<?=e('?'.http_build_query(array_merge($_GET,['view'=>'grid'])))?>">Grid</a><a class="btn small <?=$view==='list'?'':'secondary'?>" href="<?=e('?'.http_build_query(array_merge($_GET,['view'=>'list'])))?>">List</a></div></section>
<section class="grid2" id="media-upload"><form class="card form-card" method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="upload"><h2>Upload media</h2><label>Folder<select name="folder"><?php $hasGeneral=false;foreach($folders as $f):$hasGeneral=$hasGeneral||$f['folder_name']==='General';?><option value="<?=e($f['folder_name'])?>" <?=$folder===$f['folder_name']?'selected':''?>><?=e($f['folder_name'])?></option><?php endforeach;if(!$hasGeneral):?><option value="General" <?=!$folder?'selected':''?>>General</option><?php endif;?></select></label><label>Files<input id="mediaFilesPicker" type="file" name="media[]" multiple accept="image/*,audio/*,video/*,.zip,.pdf,.txt,.csv,.json,.doc,.docx,.xls,.xlsx,.ppt,.pptx"></label><div class="upload-picker-actions"><button class="btn small secondary" type="button" id="chooseMediaFolder">Choose folder</button><input id="mediaFolderPicker" type="file" multiple webkitdirectory directory accept="image/*,audio/*,video/*,.zip,.pdf,.txt,.csv,.json,.doc,.docx,.xls,.xlsx,.ppt,.pptx" hidden><span class="muted small-text">Select files, a whole folder, or drag a folder here.</span></div><div id="mediaDrop" class="media-drop">Drop files or a folder here. Large files are uploaded in small resumable chunks.</div><div id="mediaQueue" class="upload-list"></div><button class="btn">Upload selected</button></form><form class="card form-card" method="post"><?=csrf_field()?><input type="hidden" name="action" value="url"><h2>Add public URL</h2><label>Name<input name="name" required placeholder="Logo or banner"></label><label>Folder<input name="folder" value="<?=e($folder?:'General')?>"></label><label>Public URL<input name="source_url" type="url" placeholder="https://example.com/image.jpg" required></label><label>MIME type<input name="mime_type" value="image/jpeg"></label><button class="btn">Save URL</button></form></section>
<?php if($isAdmin):?><details class="card media-folder-manager"><summary><b>Folder management</b><span class="muted">Create, rename or remove empty folders</span></summary><div class="grid3"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_folder"><label>New folder<input name="folder" required placeholder="Campaigns"></label><button class="btn small">Create</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="rename_folder"><label>Rename<select name="old_folder"><?php foreach($folders as $f):?><option value="<?=e($f['folder_name'])?>"><?=e($f['folder_name'])?></option><?php endforeach;?></select></label><label>New name<input name="new_folder" required></label><button class="btn small">Rename</button></form><form method="post" onsubmit="return confirm('Delete this folder? Only empty folders can be removed.')"><?=csrf_field()?><input type="hidden" name="action" value="delete_folder"><label>Remove empty folder<select name="folder"><?php foreach($folders as $f):?><option value="<?=e($f['folder_name'])?>"><?=e($f['folder_name'])?> (<?=$f['n']?>)</option><?php endforeach;?></select></label><button class="btn small danger">Delete folder</button></form></div></details><?php endif;?>
<form method="post" id="bulkMediaForm"><?=csrf_field()?><input type="hidden" name="action" id="bulkAction" value="bulk_move"><div class="media-bulkbar card"><label class="select-all"><input type="checkbox" id="selectAll"> Select all shown</label><select name="target_folder" id="bulkFolder"><option value="">Move to folder…</option><?php foreach($folders as $f):?><option value="<?=e($f['folder_name'])?>"><?=e($f['folder_name'])?></option><?php endforeach;?></select><button class="btn small" type="submit" onclick="document.getElementById('bulkAction').value='bulk_move'">Move selected</button><?php if($isAdmin):?><button class="btn small danger" type="submit" onclick="if(!confirm('Delete selected media permanently?'))return false;document.getElementById('bulkAction').value='bulk_delete'">Delete selected</button><?php endif;?><button class="btn small secondary" type="button" id="copySelected">Copy selected URLs</button></div>
<div class="media-grid <?=$view==='list'?'media-list-view':''?>" id="media-grid">
<?php foreach($rows as $m):$url=(string)$m['source_url'];$fav=(int)($m['is_favorite']??0);?><article class="media-card" data-kind="<?=e($m['kind']??'file')?>" data-name="<?=e($m['name'])?>" data-folder="<?=e($m['folder_name'])?>" data-size="<?=e((string)($m['size_bytes']??0))?>" data-mime="<?=e((string)($m['mime_type']??''))?>"><div class="media-select"><input class="media-check" type="checkbox" name="ids[]" value="<?=$m['id']?>" data-url="<?=e($url)?>" aria-label="Select <?=e($m['name'])?>"></div><div class="media-preview" data-preview-kind="<?=e($m['kind']??'file')?>"><?php if(($m['kind']??'')==='image'):?><img src="<?=e($url)?>" alt="<?=e($m['alt_text']??$m['name'])?>" loading="lazy" data-preview-url="<?=e($url)?>"><?php elseif(($m['kind']??'')==='audio'):?><audio controls preload="metadata" src="<?=e($url)?>"></audio><?php elseif(($m['kind']??'')==='video'):?><video controls preload="metadata" src="<?=e($url)?>"></video><?php else:?><div class="file-icon"><?=($m['kind']??'')==='archive'?'🗜️':'📄'?></div><?php endif;?></div><div class="media-card-body"><div class="media-title-row"><strong title="<?=e($m['name'])?>"><?=e($m['name'])?></strong><details class="asset-menu"><summary aria-label="Asset actions">⋮</summary><div class="asset-menu-pop"><button type="button" data-copy-url="<?=e($url)?>">Share / Copy URL</button><a href="<?=e($url)?>" target="_blank" rel="noopener" download>Direct download</a><button type="button" data-open-edit>Rename / Edit</button><button type="button" data-delete-media="<?=$m['id']?>">Delete</button></div></details><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="favorite"><input type="hidden" name="id" value="<?=$m['id']?>"><button class="media-fav <?=$fav?'active':''?>" title="<?=$fav?'Remove favorite':'Add favorite'?>" aria-label="Favorite">★</button></form></div><small><?=e($m['folder_name']??'General')?> · <?=e(strtoupper($m['kind']??'file'))?> · <?=e($m['size_bytes']?storage_human((int)$m['size_bytes']):'Remote')?></small><?php if(!empty($m['width'])&&$m['height']):?><small><?=e($m['width'])?> × <?=e($m['height'])?> px</small><?php endif;?><div class="media-actions"><button class="edit-btn" type="button" data-copy-url="<?=e($url)?>">Copy URL</button><?php if(in_array($m['kind'],['image','video','audio'],true)):?><button class="edit-btn" type="button" data-open-preview>Preview</button><?php endif;?><a class="edit-btn" href="<?=e($url)?>" target="_blank" rel="noopener" <?=str_starts_with($m['source_url'],'/uploads/')?'download':''?>>Open</a></div><details class="media-edit"><summary>Edit details</summary><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=$m['id']?>"><label>Name<input name="name" value="<?=e($m['name'])?>" required></label><label>Folder<select name="folder"><?php foreach($folders as $f):?><option value="<?=e($f['folder_name'])?>" <?=$m['folder_name']===$f['folder_name']?'selected':''?>><?=e($f['folder_name'])?></option><?php endforeach;?></select></label><label>Alt text<input name="alt_text" value="<?=e($m['alt_text']??'')?>" placeholder="Useful description for images"></label><label>Description<textarea name="description" rows="3"><?=e($m['description']??'')?></textarea></label><label class="check"><input type="checkbox" name="is_favorite" value="1" <?=$fav?'checked':''?>> Favorite</label><button class="btn small">Save details</button></form></details></div></article><?php endforeach;if(!$rows):?><div class="card"><p class="muted">No media matched these filters.</p></div><?php endif;?></div></form>
<div class="media-modal" id="mediaModal" hidden><div class="media-modal-backdrop" data-close-media></div><div class="media-modal-dialog"><button class="modal-close" type="button" data-close-media>×</button><div id="mediaModalBody"></div></div></div>
<script>
(function(){
const form=document.querySelector('#media-upload form.form-card'),p=document.getElementById('mediaFilesPicker'),folderPicker=document.getElementById('mediaFolderPicker'),chooseFolder=document.getElementById('chooseMediaFolder'),q=document.getElementById('mediaQueue'),drop=document.getElementById('mediaDrop'),csrf=form?.querySelector('input[name="_csrf"]')?.value||window.POCKETCAT_CSRF||'';
if(!form||!p)return;let fs=[];
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));const sleep=ms=>new Promise(r=>setTimeout(r,ms));
async function jsonResp(r){const t=await r.text();let j;try{j=JSON.parse(t)}catch{throw Error(r.status===403?'Upload blocked by the server (HTTP 403). Switch to User View or contact the hosting provider.':'Server returned an invalid upload response (HTTP '+r.status+').')}if(!r.ok||j.ok===false)throw Error(j.error||('Upload failed (HTTP '+r.status+').'));return j}
function add(list){const seen=new Set(fs.map(f=>(f.webkitRelativePath||f.name)+'|'+f.size));for(const f of list){const k=(f.webkitRelativePath||f.name)+'|'+f.size;if(!seen.has(k)){fs.push(f);seen.add(k)}}draw()}
function draw(){q.innerHTML='';fs.forEach((f,i)=>{const d=document.createElement('div');d.className='upload-chip';d.innerHTML='<span>'+esc(f.webkitRelativePath||f.name)+' · '+(f.size/1048576).toFixed(2)+' MB</span><button type="button" aria-label="Remove">×</button>';d.querySelector('button').onclick=()=>{fs.splice(i,1);draw()};q.appendChild(d)})}
p.addEventListener('change',()=>{add([...p.files]);p.value=''});folderPicker?.addEventListener('change',()=>{add([...folderPicker.files]);folderPicker.value=''});
chooseFolder?.addEventListener('click',async()=>{if(window.showDirectoryPicker){try{const h=await showDirectoryPicker({mode:'read'});const out=[];async function walk(dir,path=''){for await(const [,v] of dir.entries()){if(v.kind==='file')out.push(await v.getFile());else if(v.kind==='directory')await walk(v,path+v.name+'/')}}await walk(h);for(const f of out){try{Object.defineProperty(f,'webkitRelativePath',{value:h.name+'/'+f.name,configurable:true})}catch{}}add(out);return}catch(e){if(e?.name==='AbortError')return}}folderPicker?.click()});
drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('dragover')});drop.addEventListener('dragleave',()=>drop.classList.remove('dragover'));drop.addEventListener('drop',async e=>{e.preventDefault();drop.classList.remove('dragover');const items=[...(e.dataTransfer?.items||[])],out=[];async function walk(entry,path=''){if(entry.isFile){await new Promise(res=>entry.file(f=>{try{Object.defineProperty(f,'webkitRelativePath',{value:path+f.name,configurable:true})}catch{}out.push(f);res()}))}else if(entry.isDirectory){const reader=entry.createReader();await new Promise(resolve=>{const read=()=>reader.readEntries(async es=>{if(!es.length)return resolve();for(const x of es)await walk(x,path+entry.name+'/');read()},()=>resolve())})}}for(const it of items){const en=it.webkitGetAsEntry?.();if(en)await walk(en);else{const f=it.getAsFile?.();if(f)out.push(f)}}add(out.length?out:[...(e.dataTransfer?.files||[])]);});
async function upload(f,card){const init=new FormData();for(const [k,v] of Object.entries({_csrf:csrf,action:'init',scope:'media',name:f.name,size:String(f.size),mime:f.type||'application/octet-stream',relative_path:f.webkitRelativePath||f.name,folder:form.querySelector('[name="folder"]')?.value||'General'}))init.append(k,v);const x=await jsonResp(await fetch('/api/upload-chunk/',{method:'POST',credentials:'same-origin',body:init}));let token=x.token,off=0,chunk=Number(x.chunk_size||262144);const status=new FormData();status.append('_csrf',csrf);status.append('action','status');status.append('token',token);const z=await jsonResp(await fetch('/api/upload-chunk/',{method:'POST',credentials:'same-origin',body:status}));off=Number(z.received||0);while(off<f.size){const end=Math.min(f.size,off+chunk);let j,last;for(let a=0;a<3;a++){const fd=new FormData();fd.append('_csrf',csrf);fd.append('action','chunk');fd.append('token',token);fd.append('offset',String(off));fd.append('chunk',f.slice(off,end,f.type||'application/octet-stream'),f.name+'.part');try{j=await jsonResp(await fetch('/api/upload-chunk/',{method:'POST',credentials:'same-origin',body:fd}));last=null;break}catch(e){last=e;await sleep(500*(a+1))}}if(last)throw last;off=Number(j.received||off);card.querySelector('.bar i').style.width=(f.size?Math.round(off/f.size*100):100)+'%';card.querySelector('.status').textContent='Uploading '+Math.round(off/f.size*100)+'%'}const done=new FormData();done.append('_csrf',csrf);done.append('action','complete');done.append('token',token);done.append('folder',form.querySelector('[name="folder"]')?.value||'General');return jsonResp(await fetch('/api/upload-chunk/',{method:'POST',credentials:'same-origin',body:done}))}
form.addEventListener('submit',async e=>{e.preventDefault();if(!fs.length){alert('Choose at least one file or folder.');return}const btn=form.querySelector('button[type="submit"]');btn.disabled=true;const files=[...fs];q.innerHTML='';let cursor=0;async function worker(){while(true){const i=cursor++;if(i>=files.length)return;const f=files[i],d=document.createElement('div');d.className='upload-card card';d.innerHTML='<b>'+esc(f.webkitRelativePath||f.name)+'</b><div class="muted status">Waiting…</div><div class="bar"><i style="width:0%"></i></div>';q.appendChild(d);try{const j=await upload(f,d);d.querySelector('.status').textContent='Uploaded';d.querySelector('i').style.width='100%';d.insertAdjacentHTML('beforeend','<a class="btn small" target="_blank" rel="noopener" href="'+esc(j.url)+'">Open</a>')}catch(err){d.querySelector('.status').textContent=err.message||'Upload failed';d.classList.add('upload-error')}}}await Promise.all([worker(),worker()]);btn.disabled=false;fs=[];setTimeout(()=>location.reload(),900)});
const all=document.getElementById('selectAll'),checks=[...document.querySelectorAll('.media-check')];all?.addEventListener('change',()=>checks.forEach(x=>x.checked=all.checked));document.getElementById('copySelected')?.addEventListener('click',async()=>{const urls=checks.filter(x=>x.checked).map(x=>x.dataset.url);if(!urls.length)return alert('Select at least one media item.');try{await navigator.clipboard.writeText(urls.join('\n'));alert(urls.length+' URL(s) copied.')}catch(e){window.prompt('Copy selected URLs',urls.join('\n'))}});
document.querySelectorAll('[data-delete-media]').forEach(b=>b.addEventListener('click',()=>{if(!confirm('Delete this media item?'))return;const f=document.createElement('form');f.method='post';f.style.display='none';f.innerHTML='<?=csrf_field()?>'+'<input type="hidden" name="action" value="delete">'+'<input type="hidden" name="id" value="'+b.dataset.deleteMedia+'">';document.body.appendChild(f);f.submit()}));document.querySelectorAll('[data-copy-url]').forEach(b=>b.addEventListener('click',async()=>{const url=b.dataset.copyUrl;try{await navigator.clipboard.writeText(url);const old=b.textContent;b.textContent='Copied';setTimeout(()=>b.textContent=old,1200)}catch(e){window.prompt('Copy public URL',url)}}));
const modal=document.getElementById('mediaModal'),body=document.getElementById('mediaModalBody');document.querySelectorAll('[data-open-edit]').forEach(b=>b.addEventListener('click',()=>b.closest('.media-card')?.querySelector('.media-edit')?.setAttribute('open','')));document.querySelectorAll('[data-close-media]').forEach(x=>x.onclick=()=>{modal.hidden=true;body.innerHTML=''});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal&&!modal.hidden){modal.hidden=true;body.innerHTML=''}});
draw();
})();
</script>
<link rel="stylesheet" href="/assets/css/universal-media-viewer-v18.css?v=18.7"><link rel="stylesheet" href="/assets/css/media-library-preferences-v18.3.css?v=18.3"><script src="/assets/js/media-library-preferences-v18.3.js?v=18.3" defer></script>
<script src="/assets/js/universal-media-viewer-v18.js?v=18.7" defer></script><?php require __DIR__.'/../includes/footer.php';
