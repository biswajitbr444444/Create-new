<?php
require __DIR__.'/../bootstrap.php';require_login();
header('X-PocketCat-AI-Endpoint: 1'); if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST required.'],405);verify_csrf();
$uid=(int)current_user()['id'];$action=(string)post('action','send');
try {
if($action==='new'){ $s=db()->prepare('INSERT INTO conversations(user_id,title,created_at,updated_at) VALUES(?,?,NOW(),NOW())');$s->execute([$uid,'New chat']);json_response(['ok'=>true,'conversation_id'=>(int)db()->lastInsertId()]); }
if($action==='history'){ $s=db()->prepare('SELECT id,title,updated_at FROM conversations WHERE user_id=? ORDER BY updated_at DESC');$s->execute([$uid]);json_response(['ok'=>true,'conversations'=>$s->fetchAll()]); }
if($action==='messages'){ $cid=(int)post('conversation_id');$s=db()->prepare('SELECT id,role,content,created_at FROM conversation_messages WHERE conversation_id=? AND EXISTS(SELECT 1 FROM conversations c WHERE c.id=? AND c.user_id=?) ORDER BY id');$s->execute([$cid,$cid,$uid]);json_response(['ok'=>true,'messages'=>$s->fetchAll()]); }
if($action==='share'){
 $cid=(int)post('conversation_id');$mid=(int)post('message_id');
 $s=db()->prepare('SELECT id FROM conversations WHERE id=? AND user_id=?');$s->execute([$cid,$uid]);if(!$s->fetch())json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
 if($mid){$s=db()->prepare('SELECT id FROM conversation_messages WHERE id=? AND conversation_id=? AND role=\'assistant\'');$s->execute([$mid,$cid]);if(!$s->fetch())json_response(['ok'=>false,'error'=>'Assistant message not found.'],404);}
 $type=$mid?'message':'conversation';$tok=hash('sha256',random_token(32));db()->prepare('INSERT INTO shared_items(token,user_id,type,conversation_id,message_id,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$tok,$uid,$type,$cid,$mid?:null,date('Y-m-d H:i:s')]);json_response(['ok'=>true,'url'=>safe_public_url('share/'.$tok)]);
}
$prompt=trim((string)post('prompt',''));if($prompt==='')json_response(['ok'=>false,'error'=>'Please enter a prompt.'],422);
$cid=(int)post('conversation_id');if(!$cid){$s=db()->prepare('INSERT INTO conversations(user_id,title,created_at,updated_at) VALUES(?,?,NOW(),NOW())');$s->execute([$uid,mb_substr($prompt,0,70)]);$cid=(int)db()->lastInsertId();}
$s=db()->prepare('SELECT id FROM conversations WHERE id=? AND user_id=?');$s->execute([$cid,$uid]);if(!$s->fetch())json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
if(!ai_is_premium(current_user())&&ai_seconds_remaining($uid,current_user())<0.001)json_response(['ok'=>false,'error'=>'Your daily AI active-time allowance is exhausted. Please upgrade your plan.'],429);
$inUnits=0;
$s=db()->prepare('SELECT role,content FROM conversation_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 18');$s->execute([$cid]);$hist=array_reverse($s->fetchAll());$input=[];foreach($hist as $m)$input[]=['role'=>$m['role'],'content'=>$m['content']];$input[]=['role'=>'user','content'=>$prompt];
$started=microtime(true);
[$ok,$answer]=ai_chat_with_input($input,post('agent','0')==='1');if(!$ok){json_response(['ok'=>false,'error'=>$answer],503);}
$inId=db()->prepare('INSERT INTO conversation_messages(conversation_id,role,content,created_at) VALUES(?,?,?,NOW())');$inId->execute([$cid,'user',$prompt]);$am=db()->prepare('INSERT INTO conversation_messages(conversation_id,role,content,created_at) VALUES(?,?,?,NOW())');$am->execute([$cid,'assistant',$answer]);$mid=(int)db()->lastInsertId();$activeSeconds=microtime(true)-$started;ai_add_active_time($uid,$activeSeconds,current_user());$outUnits=0;db()->prepare('UPDATE conversations SET title=IF(title=\'New chat\',?,title),updated_at=NOW() WHERE id=?')->execute([mb_substr($prompt,0,70),$cid]);
json_response(['ok'=>true,'conversation_id'=>$cid,'answer'=>$answer,'message_id'=>$mid,'output_units'=>$outUnits]);
} catch(Throwable $e){http_response_code(500);json_response(['ok'=>false,'error'=>'AI chat failed: '.$e->getMessage()]);}
function ai_chat_with_input(array $input,bool $agent=false):array{$c=ai_credentials((int)(current_user()['id']??0));if(ai_openai_compatible($c))return $agent?ai_chat_completions_agent_request($input,$c):ai_chat_completions_request($input,$c,4096);return $agent?ai_converse_agent_request($input,$c):ai_converse_request($input,$c);}
