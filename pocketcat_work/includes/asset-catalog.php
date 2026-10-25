<?php
declare(strict_types=1);

/**
 * User asset provenance registry.
 * Every real upload that can be surfaced in the user Media Library gets a
 * category describing the PocketCat feature that created it.
 */
function asset_category(string $source): string {
    return match(strtolower(trim($source))) {
        'storage', 'files', 'file' => 'Shared Files',
        'support', 'support_attachment' => 'Support Attachments',
        'ai', 'chat', 'agent', 'ai_upload' => 'AI Workspace',
        default => 'Media Assets',
    };
}

function asset_catalog_register(array $data): ?int {
    try {
        $uid=(int)($data['user_id']??0);
        if($uid<1) return null;
        $name=file_normalize_name((string)($data['name']??'file'));
        if($name==='') return null;
        $mime=substr((string)($data['mime_type']??'application/octet-stream'),0,150);
        $source=(string)($data['source_type']??'media');
        $category=substr((string)($data['source_category']??asset_category($source)),0,80);
        $url=(string)($data['source_url']??'');
        $storage=(string)($data['storage_path']??($url!==''?$url:''));
        $canonicalFileId=(int)($data['canonical_file_id']??0);
        $size=max(0,(int)($data['size_bytes']??0));
        $kind=(string)($data['kind']??file_kind($mime));if(!in_array($kind,['image','audio','video','archive','file'],true))$kind='file';
        $folder=trim((string)($data['folder_name']??'General'))?:'General';
        $width=isset($data['width'])?(int)$data['width']:null;
        $height=isset($data['height'])?(int)$data['height']:null;
        $pdo=db();
        $refId=(int)($data['source_ref_id']??0);
        if($refId>0){$q=$pdo->prepare('SELECT id FROM media_assets WHERE user_id=? AND source_category=? AND source_ref_id=? LIMIT 1');$q->execute([$uid,$category,$refId]);$existing=$q->fetchColumn();if($existing)return (int)$existing;}
        $q=$pdo->prepare('INSERT INTO media_assets(user_id,name,folder_name,source_type,source_category,source_ref_id,canonical_file_id,source_url,storage_path,mime_type,size_bytes,kind,width,height,auto_fit,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        $q->execute([$uid,$name,$folder,$source==='url'?'url':'upload',$category,(int)($data['source_ref_id']??0),$canonicalFileId?:null,$url,$storage,$mime,$size,$kind,$width,$height]);
        return (int)$pdo->lastInsertId();
    } catch(Throwable $e) {
        error_log('[PocketCat asset catalog] '.$e->getMessage());
        return null;
    }
}

function asset_catalog_register_media(int $uid,int $mediaId,string $name,string $folder,string $url,string $mime,int $size,string $kind,?int $width=null,?int $height=null): ?int {
    return asset_catalog_register([
        'user_id'=>$uid,'name'=>$name,'folder_name'=>$folder,'source_type'=>'media',
        'source_category'=>'Media Assets','source_ref_id'=>$mediaId,'source_url'=>$url,
        'storage_path'=>$url,'mime_type'=>$mime,'size_bytes'=>$size,'kind'=>$kind,
        'width'=>$width,'height'=>$height,
    ]);
}

function asset_catalog_register_file(int $uid,int $fileId,string $name,string $url,string $mime,int $size,string $kind,string $sourceType='storage',string $sourceCategory='Shared Files'): ?int {
    try {
        $db=db();
        $q=$db->prepare('SELECT id FROM media_assets WHERE user_id=? AND canonical_file_id=? LIMIT 1');
        $q->execute([$uid,$fileId]);
        if($existing=$q->fetchColumn()){
            $db->prepare('UPDATE media_assets SET name=?,source_type=?,source_category=?,source_ref_id=?,source_url=?,storage_path=?,mime_type=?,size_bytes=?,kind=?,updated_at=NOW() WHERE id=? AND user_id=?')
               ->execute([$name,'upload',$sourceCategory,$fileId,$url,$url,$mime,$size,$kind,(int)$existing,$uid]);
            return (int)$existing;
        }
        return asset_catalog_register([
            'user_id'=>$uid,'name'=>$name,'folder_name'=>$sourceCategory==='Media Assets'?'General':'Storage','source_type'=>$sourceType,
            'source_category'=>$sourceCategory,'source_ref_id'=>$fileId,'canonical_file_id'=>$fileId,'source_url'=>$url,
            'storage_path'=>$url,'mime_type'=>$mime,'size_bytes'=>$size,'kind'=>$kind,
        ]);
    } catch(Throwable $e) { error_log('[PocketCat asset catalog file sync] '.$e->getMessage()); return null; }
}

function asset_catalog_register_chat(int $uid,int $attachmentId,string $name,string $url,string $mime,int $size,string $kind): ?int {
    return asset_catalog_register([
        'user_id'=>$uid,'name'=>$name,'folder_name'=>'AI Workspace','source_type'=>'ai',
        'source_category'=>'AI Workspace','source_ref_id'=>$attachmentId,'source_url'=>$url,
        'storage_path'=>$url,'mime_type'=>$mime,'size_bytes'=>$size,'kind'=>$kind,
    ]);
}

function asset_catalog_register_support(int $uid,int $messageId,string $name,string $url,string $mime,int $size,string $kind): ?int {
    return asset_catalog_register([
        'user_id'=>$uid,'name'=>$name,'folder_name'=>'Support Attachments','source_type'=>'support',
        'source_category'=>'Support Attachments','source_ref_id'=>(int)sprintf('%u',crc32($url)),'source_url'=>$url,
        'storage_path'=>$url,'mime_type'=>$mime,'size_bytes'=>$size,'kind'=>$kind,
    ]);
}
