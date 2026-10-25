<?php
declare(strict_types=1);
require __DIR__.'/includes/functions.php';
if(PHP_SAPI!=='cli' && !headers_sent()){header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Expires: 0');}
require __DIR__.'/includes/policy.php'; require __DIR__.'/includes/plan-catalog.php'; require __DIR__.'/includes/database.php'; require __DIR__.'/includes/csrf.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/admin-auth.php'; require __DIR__.'/includes/mail.php'; require __DIR__.'/includes/ai.php'; require __DIR__.'/includes/links.php'; require __DIR__.'/includes/maxmind-reader.php'; require __DIR__.'/includes/geoip-updater.php'; require __DIR__.'/includes/geo.php'; require __DIR__.'/includes/files.php'; require __DIR__.'/includes/upload-engine.php'; require __DIR__.'/includes/asset-catalog.php'; require __DIR__.'/includes/cloud-storage.php'; require __DIR__.'/includes/billing.php'; require __DIR__.'/includes/code.php'; require __DIR__.'/includes/visual-engine.php'; require __DIR__.'/includes/editor-suite.php'; require __DIR__.'/includes/menu-manager.php'; require __DIR__.'/includes/qr.php'; require __DIR__.'/includes/docs.php'; require __DIR__.'/includes/cms.php'; require __DIR__.'/includes/analytics.php';
if(function_exists('upload_prepare_directories')){try{upload_prepare_directories();}catch(Throwable $e){error_log('[PocketCat upload dirs] '.$e->getMessage());}}
if(session_status()!==PHP_SESSION_ACTIVE){$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);session_start();}
enforce_session_idle_timeout();
if(!is_installed()){
    $requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    if(strpos(rtrim($requestPath,'/'),'/install')!==0)redirect('/install/');
}
// Expensive schema compatibility checks run once per schema-code version, not on every request.
// Delete storage/.schema-check-version to force a safe re-check after a manual deployment.
$schemaCheckVersion='19.68-schema-1';
$schemaMarker=__DIR__.'/storage/.schema-check-version';
$schemaChecked=is_file($schemaMarker) && trim((string)@file_get_contents($schemaMarker))===$schemaCheckVersion;
if(is_installed() && !$schemaChecked){
    try {
        ensure_platform_schema();
        if(function_exists('ensure_advanced_analytics_schema')) ensure_advanced_analytics_schema(db());
        ensure_code_schema(); ensure_visual_schema(); editor_suite_schema();
        if(function_exists('ensure_file_workspace_schema')) ensure_file_workspace_schema();
        menu_schema(); qr_schema(); docs_schema(); ensure_cms_schema();
        $dir=dirname($schemaMarker); if(!is_dir($dir)) @mkdir($dir,0750,true);
        if(is_dir($dir) && is_writable($dir)) @file_put_contents($schemaMarker,$schemaCheckVersion,LOCK_EX);
    } catch(Throwable $e) { error_log('[PocketCat schema bootstrap] '.$e->getMessage()); }
}
if (is_installed() && function_exists('cms_publish_scheduled')) { try { cms_publish_scheduled(); } catch(Throwable $e) { error_log('[PocketCat CMS scheduler] '.$e->getMessage()); } }
if(function_exists('policy_flag') && policy_flag('maintenance_mode',false)){ $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; $allowed=(strpos($path,'/admin')===0||strpos($path,'/login')===0||strpos($path,'/logout')===0||strpos($path,'/install')===0); if(!$allowed && PHP_SAPI!=='cli'){http_response_code(503);header('Retry-After: 300');exit('PocketCat is temporarily under maintenance.');}}
