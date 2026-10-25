<?php
declare(strict_types=1);require __DIR__.'/../bootstrap.php';if(PHP_SAPI!=='cli')exit("CLI only\n");$s=db()->query("SELECT * FROM file_objects WHERE status='deleted' OR (trashed_at IS NULL AND (status='expired' OR (expires_at IS NOT NULL AND expires_at<NOW()))) LIMIT 200");$n=0;while($f=$s->fetch()){try{$id=(int)$f['id'];$db=db();$db->beginTransaction();$lock=$db->prepare("SELECT id,object_key,storage_driver FROM file_objects WHERE id=? AND (status='deleted' OR (trashed_at IS NULL AND (status='expired' OR (expires_at IS NOT NULL AND expires_at<NOW())))) FOR UPDATE");$lock->execute([$id,$id]);$locked=$lock->fetch();if(!$locked){$db->rollBack();continue;}$db->prepare('DELETE FROM file_versions WHERE file_id=?')->execute([$id]);$refs=$db->prepare('SELECT COUNT(*) FROM file_versions WHERE object_key=?');$refs->execute([(string)$f['object_key']]);if((int)$refs->fetchColumn()===0){if(($f['storage_driver']??'local')==='local'){$path=file_local_path($f);$root=realpath(file_local_root());$real=realpath($path);if($real&&$root&&str_starts_with($real,$root.DIRECTORY_SEPARATOR)&&is_file($real))@unlink($real);}elseif(($f['storage_driver']??'')==='s3'&&s3_cfg_ok())s3_request('DELETE',(string)$f['object_key']);}$db->prepare("UPDATE file_objects SET status='deleted',updated_at=NOW() WHERE id=?")->execute([$id]);$db->commit();$n++;}catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('[PocketCat file cleanup] '.$e->getMessage());}}try{$db=db();$fileDays=max(1,(int)policy_global('file_log_retention_days',90));$linkDays=max(1,(int)policy_global('link_log_retention_days',365));$db->exec("DELETE FROM file_events WHERE occurred_at < DATE_SUB(NOW(), INTERVAL {$fileDays} DAY)");$db->exec("DELETE FROM link_clicks WHERE clicked_at < DATE_SUB(NOW(), INTERVAL {$linkDays} DAY)");$db->exec("DELETE FROM link_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)");$db->exec("DELETE FROM policy_user_overrides WHERE expires_at IS NOT NULL AND expires_at<NOW()");}catch(Throwable $e){}// V19.1 File Storage Trash retention. 0 means never; invalid values fall back to 30 days.
try {
    $raw=function_exists('setting')?setting('file_trash_retention_days','30'):'30';
    $retention=(int)$raw;if(!in_array($retention,[0,7,14,30,60,90],true))$retention=30;
    if($retention>0){$db=db();$q=$db->prepare("SELECT id,user_id,object_key FROM file_objects WHERE trashed_at IS NOT NULL AND trashed_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND status<>'deleted' ORDER BY trashed_at ASC LIMIT 100");$q->execute([$retention]);foreach($q->fetchAll() as $f){$id=(int)$f['id'];$uid=(int)$f['user_id'];try{$db->beginTransaction();$lock=$db->prepare("SELECT id FROM file_objects WHERE id=? AND user_id=? AND trashed_at IS NOT NULL AND status<>'deleted' FOR UPDATE");$lock->execute([$id,$uid]);if(!$lock->fetchColumn()){$db->rollBack();continue;}$db->prepare('UPDATE file_share_links s JOIN file_share_items i ON i.share_id=s.id SET s.revoked_at=COALESCE(s.revoked_at,NOW()),s.updated_at=NOW() WHERE i.file_id=?')->execute([$id]);$db->prepare('DELETE FROM file_share_items WHERE file_id=?')->execute([$id]);$db->prepare('DELETE FROM file_versions WHERE file_id=?')->execute([$id]);try{$db->prepare('DELETE FROM media_assets WHERE user_id=? AND canonical_file_id=?')->execute([$uid,$id]);$db->prepare("INSERT INTO storage_audit_events(user_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,'RETENTION_DELETE','file',?,NOW())")->execute([$uid,$uid,$id]);}catch(Throwable $ignored){}$db->prepare("UPDATE file_objects SET status='deleted',updated_at=NOW() WHERE id=? AND user_id=? AND trashed_at IS NOT NULL")->execute([$id,$uid]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('[PocketCat file trash cleanup] '.$e->getMessage());}}}
} catch(Throwable $e){error_log('[PocketCat file trash cleanup] '.$e->getMessage());}
// V18.4 media trash retention. Configure media_trash_retention_days as 7, 14, 30, 60, 90, or 0 (never).
try {
    $raw = function_exists('setting') ? setting('media_trash_retention_days', '30') : '30';
    $retention = (int)$raw;
    if (!in_array($retention, [0,7,14,30,60,90], true)) $retention = 30;
    if ($retention > 0) {
        $db = db();
        $q = $db->prepare("SELECT id,user_id,canonical_file_id,storage_path FROM media_assets WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY deleted_at ASC LIMIT 100");
        $q->execute([$retention]);
        foreach ($q->fetchAll() as $m) {
            $id=(int)$m['id']; $uid=(int)$m['user_id'];
            try {
                $db->beginTransaction();
                $lock=$db->prepare('SELECT id FROM media_assets WHERE id=? AND user_id=? AND deleted_at IS NOT NULL FOR UPDATE');
                $lock->execute([$id,$uid]);
                if (!$lock->fetchColumn()) { $db->rollBack(); continue; }
                if (!empty($m['canonical_file_id'])) {
                    $refs=$db->prepare('SELECT COUNT(*) FROM media_assets WHERE canonical_file_id=? AND id<>?');
                    $refs->execute([(int)$m['canonical_file_id'],$id]);
                    $shareRefs=$db->prepare('SELECT COUNT(*) FROM file_share_items WHERE file_id=?'); $shareRefs->execute([(int)$m['canonical_file_id']]);
                    if ((int)$refs->fetchColumn()===0 && (int)$shareRefs->fetchColumn()===0) {
                        $fo=$db->prepare('SELECT * FROM file_objects WHERE id=? AND user_id=?');
                        $fo->execute([(int)$m['canonical_file_id'],$uid]); $obj=$fo->fetch();
                        if ($obj && ($obj['storage_driver']??'')==='local') { $fp=file_local_path($obj); if (is_file($fp)) @unlink($fp); }
                        if ($obj) $db->prepare("UPDATE file_objects SET status='deleted',updated_at=NOW() WHERE id=? AND user_id=?")->execute([(int)$m['canonical_file_id'],$uid]);
                    }
                } elseif (!empty($m['storage_path']) && str_starts_with((string)$m['storage_path'],'/uploads/media/')) {
                    $path=parse_url((string)$m['storage_path'],PHP_URL_PATH); $root=realpath(__DIR__.'/../uploads/media/'.$uid); $full=realpath(__DIR__.'/..'.(string)$path);
                    if ($root && $full && str_starts_with($full,$root.DIRECTORY_SEPARATOR) && is_file($full)) @unlink($full);
                }
                $db->prepare('DELETE FROM media_assets WHERE id=? AND user_id=? AND deleted_at IS NOT NULL')->execute([$id,$uid]);
                $db->prepare("INSERT INTO media_audit_events(user_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,NOW())")->execute([$uid,'RETENTION_DELETE','media',$id]);
                $db->commit();
            } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); error_log('[PocketCat media trash cleanup] '.$e->getMessage()); }
        }
    }
} catch (Throwable $e) { error_log('[PocketCat media trash cleanup] '.$e->getMessage()); }
echo "cleaned {$n}\n";
