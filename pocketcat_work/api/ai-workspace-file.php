<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();

$uid=(int)current_user()['id'];
$attachmentId=(int)($_GET['attachment_id']??0);
$downloadZip=($_GET['download']??'')==='zip';
if($attachmentId>0){
    $attachment=ai_owned_chat_attachment($attachmentId,$uid);
    if(!$attachment||!(strtolower((string)$attachment['kind'])==='archive'||str_ends_with(strtolower((string)$attachment['name']),'.zip'))){http_response_code(404);exit('Not found');}
    $workspace=ai_private_attachment_workspace($uid,$attachmentId);
    $workspaceRoot=realpath($workspace.'/extracted');
    $path=$downloadZip?realpath($workspace.'/updated.zip'):($workspaceRoot&&ai_safe_workspace_file((string)($_GET['path']??''))?realpath($workspaceRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$_GET['path'])):false);
    $protectedRoot=$downloadZip?realpath(dirname($workspace)):$workspaceRoot;
}else{
    $conversationId=(int)($_GET['conversation_id']??0);
    $conversationQuery=db()->prepare('SELECT id,project_id FROM conversations WHERE id=? AND user_id=?');
    $conversationQuery->execute([$conversationId,$uid]);
    $conversation=$conversationQuery->fetch();
    $projectId=(int)($_GET['project_id']??0);
    if(!$conversation||$projectId!==(int)($conversation['project_id']??0)){http_response_code(404);exit('Not found');}
    try{$workspaceRoot=realpath(ai_agent_workspace_root($uid,$conversationId,$projectId));}catch(Throwable $e){http_response_code(404);exit('Not found');}
    $path=$downloadZip?realpath($workspaceRoot.'.zip'):($workspaceRoot&&ai_safe_workspace_file((string)($_GET['path']??''))?realpath($workspaceRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$_GET['path'])):false);
    $protectedRoot=$downloadZip?realpath(dirname($workspaceRoot)):$workspaceRoot;
}
if(!$path||!$protectedRoot||!is_file($path)||!str_starts_with($path,rtrim($protectedRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)){
    http_response_code(404);exit('Not found');
}

$filename=basename($path);
$mime=$filename==='updated.zip'?'application/zip':'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.addcslashes($filename,'\\"').'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
