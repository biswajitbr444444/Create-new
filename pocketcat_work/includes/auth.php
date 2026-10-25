<?php
function current_user():?array{
    static $cache=[];
    $id=(int)($_SESSION['impersonated_user_id']??$_SESSION['user_id']??0);
    if(!$id)return null;
    $key=$id.':'.((int)($_SESSION['impersonated_user_id']??0));if(isset($cache[$key]))return $cache[$key];
    try{$s=db()->prepare("SELECT id,full_name,email,username,role,plan,status,timezone,last_seen_at FROM users WHERE id=? AND status='active'");$s->execute([$id]);$u=$s->fetch()?:null;
        if($u){$u['_impersonating']=!empty($_SESSION['impersonated_user_id']);$u['_original_admin_id']=(int)($_SESSION['impersonator_id']??0);$last=$_SESSION['_last_seen_write_'.$id]??0;if(time()-$last>=60){try{db()->prepare('UPDATE users SET last_seen_at=NOW(),updated_at=updated_at WHERE id=?')->execute([$id]);$_SESSION['_last_seen_write_'.$id]=time();}catch(Throwable $e){}}}
        return $cache[$key]=$u;
    }catch(Throwable $e){return null;}
}
function enforce_session_idle_timeout(?int $now=null,?int $timeoutSeconds=null):bool{
    $now??=time();
    if($timeoutSeconds===null){
        $configured=getenv('SESSION_IDLE_TIMEOUT');
        $timeoutSeconds=is_string($configured)&&ctype_digit($configured)&&(int)$configured>0?(int)$configured:1800;
    }
    $userId=(int)($_SESSION['impersonated_user_id']??$_SESSION['user_id']??0);
    $lastActivity=$_SESSION['_last_activity_at']??null;
    if($userId>0&&is_numeric($lastActivity)&&$now-(int)$lastActivity >= $timeoutSeconds){
        $_SESSION=[];
        if(session_status()===PHP_SESSION_ACTIVE&&!headers_sent())session_regenerate_id(true);
        $_SESSION['_last_activity_at']=$now;
        log_event('session_expired',$userId);
        return true;
    }
    $_SESSION['_last_activity_at']=$now;
    return false;
}
function require_login():void{if(!current_user())redirect('/login/');}
function login_user(array $u):void{session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['admin_view']=false;unset($_SESSION['impersonated_user_id'],$_SESSION['impersonator_id']);log_event('login',(int)$u['id']);}
function logout_user():void{$_SESSION=[];session_destroy();}
