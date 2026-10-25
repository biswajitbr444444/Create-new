<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();

header('X-PocketCat-AI-Endpoint: 1');
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST required.'],405);
verify_csrf();

$uid=(int)current_user()['id'];
$action=(string)post('action','send');

function ai_chat_project_for_user(int $projectId,int $uid):?array{
    if($projectId<=0)return null;
    $s=db()->prepare('SELECT id,name,description,status FROM projects WHERE id=? AND user_id=?');
    $s->execute([$projectId,$uid]);
    $project=$s->fetch();
    return $project?:null;
}
function ai_chat_owned_conversation(int $conversationId,int $uid):?array{
    $s=db()->prepare('SELECT id,title,project_id,model_id,is_pinned FROM conversations WHERE id=? AND user_id=?');
    $s->execute([$conversationId,$uid]);
    $row=$s->fetch();
    return $row?:null;
}
function ai_chat_model(string $requested,int $uid,?array $conversation=null):string{
    $model=trim($requested);
    if($model===''&&$conversation)$model=trim((string)($conversation['model_id']??''));
    if($model==='' )$model=ai_user_model_preference($uid);
    if($model==='' )$model=(string)ai_credentials($uid)['model'];
    if(!ai_valid_model_id($model))throw new InvalidArgumentException('The selected model ID is invalid.');
    return $model;
}

try{
    if($action==='history'){
        $s=db()->prepare('SELECT c.id,c.title,c.updated_at,c.project_id,c.model_id,c.is_pinned,p.name AS project_name FROM conversations c LEFT JOIN projects p ON p.id=c.project_id AND p.user_id=c.user_id WHERE c.user_id=? ORDER BY c.is_pinned DESC,c.updated_at DESC');
        $s->execute([$uid]);
        $conversations=$s->fetchAll();
        foreach($conversations as &$conversation)if(ai_is_legacy_error_html((string)$conversation['title']))$conversation['title']='Previous AI request error';
        unset($conversation);
        json_response(['ok'=>true,'conversations'=>$conversations]);
    }
    if($action==='projects'){
        $s=db()->prepare('SELECT p.id,p.name,p.description,p.status,COALESCE(x.custom_instructions,\'\') AS custom_instructions,(SELECT COUNT(*) FROM conversations c WHERE c.project_id=p.id AND c.user_id=p.user_id) AS chat_count,(SELECT COUNT(*) FROM tasks t WHERE t.project_id=p.id AND t.user_id=p.user_id) AS task_count FROM projects p LEFT JOIN ai_project_settings x ON x.project_id=p.id AND x.user_id=p.user_id WHERE p.user_id=? ORDER BY p.updated_at DESC,p.id DESC');
        $s->execute([$uid]);
        json_response(['ok'=>true,'projects'=>$s->fetchAll()]);
    }
    if($action==='project-create'){
        if(plan_limit_reached('projects',$uid))json_response(['ok'=>false,'error'=>'Project plan limit reached.'],429);
        $name=trim((string)post('name',''));
        if($name==='')json_response(['ok'=>false,'error'=>'Enter a project name.'],422);
        db()->prepare("INSERT INTO projects(user_id,name,description,status,created_at,updated_at) VALUES(?,?,?,'Planning',NOW(),NOW())")->execute([$uid,mb_substr($name,0,180),mb_substr(trim((string)post('description','')),0,5000)]);
        $projectId=(int)db()->lastInsertId();
        $instructions=mb_substr(trim((string)post('custom_instructions','')),0,10000);
        db()->prepare('INSERT INTO ai_project_settings(project_id,user_id,custom_instructions,context_text,updated_at) VALUES(?,?,?,NULL,NOW())')->execute([$projectId,$uid,$instructions]);
        json_response(['ok'=>true,'project_id'=>$projectId]);
    }
    if($action==='project-save'){
        $projectId=(int)post('project_id');
        if(!ai_chat_project_for_user($projectId,$uid))json_response(['ok'=>false,'error'=>'Project not found.'],404);
        $instructions=mb_substr(trim((string)post('custom_instructions','')),0,10000);
        db()->prepare('INSERT INTO ai_project_settings(project_id,user_id,custom_instructions,context_text,updated_at) VALUES(?,?,?,NULL,NOW()) ON DUPLICATE KEY UPDATE custom_instructions=VALUES(custom_instructions),updated_at=NOW()')->execute([$projectId,$uid,$instructions]);
        json_response(['ok'=>true]);
    }
    if($action==='project-view'){
        $projectId=(int)post('project_id');
        $project=ai_chat_project_for_user($projectId,$uid);
        if(!$project)json_response(['ok'=>false,'error'=>'Project not found.'],404);
        $s=db()->prepare('SELECT id,title,updated_at,is_pinned,model_id FROM conversations WHERE project_id=? AND user_id=? ORDER BY is_pinned DESC,updated_at DESC');
        $s->execute([$projectId,$uid]);
        $tasks=db()->prepare('SELECT id,title,status,priority,due_date FROM tasks WHERE project_id=? AND user_id=? ORDER BY updated_at DESC LIMIT 100');
        $tasks->execute([$projectId,$uid]);
        $settings=db()->prepare('SELECT custom_instructions FROM ai_project_settings WHERE project_id=? AND user_id=?');
        $settings->execute([$projectId,$uid]);
        json_response(['ok'=>true,'project'=>$project,'instructions'=>(string)($settings->fetchColumn()?:''),'chats'=>$s->fetchAll(),'tasks'=>$tasks->fetchAll()]);
    }
    if($action==='model-preference'){
        $model=ai_chat_model((string)post('model_id',''),$uid);
        $conversationId=(int)post('conversation_id');
        if($conversationId){
            if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
            db()->prepare('UPDATE conversations SET model_id=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$model,$conversationId,$uid]);
        }
        ai_save_user_model_preference($uid,$model);
        json_response(['ok'=>true,'model_id'=>$model]);
    }
    if($action==='new'){
        $projectId=(int)post('project_id');
        if($projectId&&!ai_chat_project_for_user($projectId,$uid))json_response(['ok'=>false,'error'=>'Project not found.'],404);
        $model=ai_chat_model((string)post('model_id',''),$uid);
        $title='New chat';
        db()->prepare('INSERT INTO conversations(user_id,project_id,title,model_id,is_pinned,created_at,updated_at) VALUES(?,?,?,?,0,NOW(),NOW())')->execute([$uid,$projectId?:null,$title,$model]);
        ai_save_user_model_preference($uid,$model);
        json_response(['ok'=>true,'conversation_id'=>(int)db()->lastInsertId(),'model_id'=>$model]);
    }
    if($action==='messages'){
        $conversationId=(int)post('conversation_id');
        $conversation=ai_chat_owned_conversation($conversationId,$uid);
        if(!$conversation)json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        $s=db()->prepare('SELECT id,role,content,created_at FROM conversation_messages WHERE conversation_id=? ORDER BY id');
        $s->execute([$conversationId]);
        $rows=$s->fetchAll();
        $aq=db()->prepare('SELECT id,message_id,name,stored_path,mime_type,size_bytes,kind FROM chat_attachments WHERE conversation_id=? AND user_id=? AND message_id IS NOT NULL ORDER BY id');
        $aq->execute([$conversationId,$uid]);
        $attachmentsByMessage=[];
        foreach($aq->fetchAll() as $attachment){
            $messageId=(int)$attachment['message_id'];
            $attachment['url']='/api/chat-attachment.php?id='.(int)$attachment['id'];
            $attachmentsByMessage[$messageId][]=$attachment;
        }
        foreach($rows as &$row){
            $content=(string)$row['content'];
            $legacyError=$row['role']==='assistant'&&ai_is_legacy_error_html($content);
            if($legacyError)$row['content']='The previous AI request failed because the server returned an invalid error page. That page has been hidden.';
            $row['legacy_error']=$legacyError;
            $row['attachments']=$attachmentsByMessage[(int)$row['id']]??[];
        }
        unset($row);
        json_response(['ok'=>true,'conversation'=>$conversation,'messages'=>$rows]);
    }
    if($action==='rename'){
        $conversationId=(int)post('conversation_id');
        if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        $title=trim((string)post('title',''));
        if($title==='')json_response(['ok'=>false,'error'=>'Enter a chat title.'],422);
        db()->prepare('UPDATE conversations SET title=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([mb_substr($title,0,180),$conversationId,$uid]);
        json_response(['ok'=>true]);
    }
    if($action==='pin'){
        $conversationId=(int)post('conversation_id');
        if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        db()->prepare('UPDATE conversations SET is_pinned=IF(is_pinned=1,0,1),updated_at=NOW() WHERE id=? AND user_id=?')->execute([$conversationId,$uid]);
        json_response(['ok'=>true]);
    }
    if($action==='move'){
        $conversationId=(int)post('conversation_id');
        if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        $projectId=(int)post('project_id');
        if($projectId&&!ai_chat_project_for_user($projectId,$uid))json_response(['ok'=>false,'error'=>'Project not found.'],404);
        db()->prepare('UPDATE conversations SET project_id=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$projectId?:null,$conversationId,$uid]);
        json_response(['ok'=>true]);
    }
    if($action==='delete'){
        $conversationId=(int)post('conversation_id');
        if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        db()->prepare('DELETE FROM conversations WHERE id=? AND user_id=?')->execute([$conversationId,$uid]);
        json_response(['ok'=>true]);
    }
    if($action==='share'){
        $conversationId=(int)post('conversation_id');
        $messageId=(int)post('message_id');
        if(!ai_chat_owned_conversation($conversationId,$uid))json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
        if($messageId){
            $s=db()->prepare('SELECT id FROM conversation_messages WHERE id=? AND conversation_id=? AND role=\'assistant\'');
            $s->execute([$messageId,$conversationId]);
            if(!$s->fetch())json_response(['ok'=>false,'error'=>'Assistant message not found.'],404);
        }
        $type=$messageId?'message':'conversation';
        $token=hash('sha256',random_token(32));
        db()->prepare('INSERT INTO shared_items(token,user_id,type,conversation_id,message_id,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$token,$uid,$type,$conversationId,$messageId?:null]);
        json_response(['ok'=>true,'url'=>safe_public_url('share/'.$token)]);
    }

    $prompt=trim((string)post('prompt',''));
    $rawAttachmentIds=post('attachment_ids','');
    $attachmentIds=is_array($rawAttachmentIds)
        ?array_values(array_unique(array_filter(array_map('intval',$rawAttachmentIds))))
        :(trim((string)$rawAttachmentIds)!==''?array_values(array_unique(array_filter(array_map('intval',preg_split('/[,\s]+/',(string)$rawAttachmentIds))))):[]);
    if($prompt===''&&!$attachmentIds)json_response(['ok'=>false,'error'=>'Please enter a prompt or attach a file.'],422);
    $conversationId=(int)post('conversation_id');
    if(!$conversationId){
        $model=ai_chat_model((string)post('model_id',''),$uid);
        $projectId=(int)post('project_id');
        if($projectId&&!ai_chat_project_for_user($projectId,$uid))json_response(['ok'=>false,'error'=>'Project not found.'],404);
        db()->prepare('INSERT INTO conversations(user_id,project_id,title,model_id,is_pinned,created_at,updated_at) VALUES(?,?,?,?,0,NOW(),NOW())')->execute([$uid,$projectId?:null,mb_substr($prompt?:'Attached files',0,70),$model]);
        $conversationId=(int)db()->lastInsertId();
    }
    $conversation=ai_chat_owned_conversation($conversationId,$uid);
    if(!$conversation)json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
    if(!ai_is_premium(current_user())&&ai_seconds_remaining($uid,current_user())<0.001)json_response(['ok'=>false,'error'=>'Your daily AI active-time allowance is exhausted. Please upgrade your plan.'],429);

    $attachments=[];
    if($attachmentIds){
        $marks=implode(',',array_fill(0,count($attachmentIds),'?'));
        $q=db()->prepare("SELECT id,name,stored_path,storage_path,mime_type,size_bytes,kind FROM chat_attachments WHERE conversation_id=? AND user_id=? AND message_id IS NULL AND id IN ($marks) ORDER BY id");
        $q->execute(array_merge([$conversationId,$uid],$attachmentIds));
        $attachments=$q->fetchAll();
        if(count($attachments)!==count($attachmentIds))json_response(['ok'=>false,'error'=>'One or more attachments are no longer available. Please attach them again.'],422);
    }
    $model=ai_chat_model((string)post('model_id',''),$uid,$conversation);
    ai_save_user_model_preference($uid,$model);
    db()->prepare('UPDATE conversations SET model_id=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$model,$conversationId,$uid]);

    $promptParts=$prompt;
    foreach($attachments as $attachment)$promptParts.="\n\nAttached file #".(int)$attachment['id'].": ".$attachment['name']." (".$attachment['kind'].", ".$attachment['mime_type'].", ".(int)$attachment['size_bytes']." bytes).";
    $s=db()->prepare('SELECT role,content FROM conversation_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 18');
    $s->execute([$conversationId]);
    $history=array_reverse($s->fetchAll());
    $input=[];
    if(post('agent','0')==='1'){
        $input[]=['role'=>'system','content'=>'You are PocketCat Agent. Use only the provided PocketCat workspace tools, and only perform persistent actions the user explicitly asks for. Never claim an action succeeded unless its tool result confirms success. Attached images are supplied as vision content when supported. For a ZIP attachment, use inspect_chat_attachment to list it; use extract_chat_zip only when the user asks to extract it. After extraction, read_extracted_file and save_extracted_file are limited to safe text/source files in that private extraction workspace; package_chat_zip returns a protected download URL. read_workspace_file, write_workspace_file, delete_workspace_file, and package_workspace_files are scoped to the current private chat or project workspace and safe text/source files; only call write/delete/package when requested. Never execute uploaded files or run shell commands. Include returned download URLs as links when useful.'];
    }
    $projectId=(int)($conversation['project_id']??0);
    if($projectId){
        $settings=db()->prepare('SELECT custom_instructions,context_text FROM ai_project_settings WHERE project_id=? AND user_id=?');
        $settings->execute([$projectId,$uid]);
        $projectContext=$settings->fetch();
        $system=trim((string)($projectContext['custom_instructions']??'')."\n".(string)($projectContext['context_text']??''));
        if($system!=='')$input[]=['role'=>'system','content'=>mb_substr($system,0,15000)];
    }
    foreach($history as $message)$input[]=['role'=>$message['role'],'content'=>$message['content']];
    $contentBlocks=[['type'=>'text','text'=>$promptParts!==''?$promptParts:'Please inspect the attached files and describe what you find.']];
    foreach($attachments as $attachment){
        $mime=strtolower((string)$attachment['mime_type']);
        if(!in_array($mime,['image/jpeg','image/png','image/gif','image/webp'],true)||(int)$attachment['size_bytes']>5*1024*1024)continue;
        $publicPath=(string)($attachment['storage_path']??'');
        $pathPart=parse_url($publicPath,PHP_URL_PATH);
        if(!is_string($pathPart)||!preg_match('~^/uploads/media/'.preg_quote((string)$uid,'~').'/chat/[A-Za-z0-9._-]+$~D',$pathPart))continue;
        $root=realpath(__DIR__.'/../uploads/media/'.$uid.'/chat');
        $real=realpath(__DIR__.'/..'.$pathPart);
        if(!$root||!$real||!str_starts_with($real,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))continue;
        $bytes=file_get_contents($real);
        if($bytes===false||strlen($bytes)>5*1024*1024)continue;
        $contentBlocks[]=['type'=>'image_url','image_url'=>['url'=>'data:'.$mime.';base64,'.base64_encode($bytes)]];
    }
    $input[]=['role'=>'user','content'=>$contentBlocks];
    $started=microtime(true);
    $GLOBALS['ai_agent_context']=['user_id'=>$uid,'conversation_id'=>$conversationId,'project_id'=>$projectId];
    [$ok,$answer]=ai_chat_with_input($input,post('agent','0')==='1',$model);
    if(!$ok)json_response(['ok'=>false,'error'=>ai_safe_provider_error((string)$answer)],503);

    $inId=db()->prepare('INSERT INTO conversation_messages(conversation_id,role,content,created_at) VALUES(?,?,?,NOW())');
    $inId->execute([$conversationId,'user',$promptParts]);
    $userMessageId=(int)db()->lastInsertId();
    if($attachments){
        $q=db()->prepare('UPDATE chat_attachments SET message_id=? WHERE id=? AND conversation_id=? AND user_id=? AND message_id IS NULL');
        foreach($attachments as $attachment)$q->execute([$userMessageId,(int)$attachment['id'],$conversationId,$uid]);
    }
    $am=db()->prepare('INSERT INTO conversation_messages(conversation_id,role,content,created_at) VALUES(?,?,?,NOW())');
    $am->execute([$conversationId,'assistant',$answer]);
    $messageId=(int)db()->lastInsertId();
    ai_add_active_time($uid,microtime(true)-$started,current_user());
    db()->prepare('UPDATE conversations SET title=IF(title=\'New chat\',?,title),updated_at=NOW() WHERE id=? AND user_id=?')->execute([mb_substr($prompt?:'Attached files',0,70),$conversationId,$uid]);
    json_response(['ok'=>true,'conversation_id'=>$conversationId,'answer'=>$answer,'message_id'=>$messageId,'model_id'=>$model,'attachments'=>array_map(fn($a)=>['id'=>(int)$a['id'],'name'=>$a['name'],'mime'=>$a['mime_type'],'size'=>(int)$a['size_bytes'],'kind'=>$a['kind']],$attachments)]);
}catch(Throwable $e){
    error_log('[PocketCat AI chat] '.$e);
    http_response_code(500);
    json_response(['ok'=>false,'error'=>$e instanceof InvalidArgumentException?$e->getMessage():'AI chat is temporarily unavailable. Please try again later.']);
}

function ai_chat_with_input(array $input,bool $agent=false,?string $model=null):array{
    $credentials=ai_credentials((int)(current_user()['id']??0));
    if($model!==null&&ai_valid_model_id($model))$credentials['model']=$model;
    if(ai_openai_compatible($credentials))return $agent?ai_chat_completions_agent_request($input,$credentials):ai_chat_completions_request($input,$credentials,4096);
    return $agent?ai_converse_agent_request($input,$credentials):ai_converse_request($input,$credentials);
}
