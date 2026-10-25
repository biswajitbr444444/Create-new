<?php
require __DIR__.'/../includes/ai.php';

$checks=0;
function ai_check(bool $condition,string $message):void{
    global $checks;
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
    echo "PASS: {$message}\n";
    $checks++;
}

$root=dirname(__DIR__);
$workspaceJs=file_get_contents($root.'/assets/js/ai-workspace.js');
$catalogJs=file_get_contents($root.'/assets/js/ai-model-catalog.js');
$generatorJs=file_get_contents($root.'/assets/js/ai-worker-generator.js');
$api=file_get_contents($root.'/api/ai-chat.php');
$schema=file_get_contents($root.'/database/schema.sql');
$settings=file_get_contents($root.'/settings/ai.php');
$admin=file_get_contents($root.'/admin/ai.php');
$ai=file_get_contents($root.'/includes/ai.php');

ai_check(str_contains($workspaceJs,"fetch('/api/ai-chat.php'")&&!str_contains($workspaceJs,"fetch('/service/ai/'"),'AI workspace requests use an explicit PHP endpoint');
ai_check(str_contains($workspaceJs,'textContent = String(text || \'\')')&&str_contains($api,'ai_is_legacy_error_html'),'chat content is inserted as text and legacy HTML error bodies are hidden');
ai_check(ai_is_legacy_error_html('<!doctype html><style>403 Forbidden</style>')&&ai_safe_provider_error('<html><script>secret()</script></html>')==='The AI provider returned an error. Check the connection settings and server logs.','HTML provider errors are replaced with a concise safe message');
ai_check(ai_valid_model_id('provider/model:latest')&&!ai_valid_model_id('model id'),'custom model IDs are validated');
ai_check(str_contains(ai_openai_base_url(['endpoint'=>'https://router.example/v1/','model'=>'custom','region'=>'us-east-1']),'https://router.example/v1'),'OpenAI-compatible base URLs preserve the version path without a trailing slash');

preg_match_all("/\\['[^']+', \\[([^\\]]+)\\]\\]/",$catalogJs,$groups);
$models=[];
foreach($groups[1] as $group)preg_match_all("/'([a-zA-Z0-9][a-zA-Z0-9.-]+)'/",$group,$found)&&array_push($models,...$found[1]);
ai_check(count($models)===77&&count(array_unique($models))===77,'the model selector contains all 77 unique supplied model IDs');
ai_check(str_contains($workspaceJs,'ai-model-search')&&str_contains($workspaceJs,'ai-use-custom-model'),'chat model selection supports live search and custom IDs');
ai_check(str_contains($generatorJs,'const upstreamUrl = base + endpoint')&&str_contains($generatorJs,'x-pocketcat-relay-token')&&str_contains($generatorJs,'CONFIG.upstreamApiKey'),'generated relay forwards the incoming model request using the configured key and relay token');
ai_check(str_contains($settings,'ai-worker-relay-tools.php')&&str_contains($admin,'ai-worker-relay-tools.php'),'user and administrator AI settings share the Worker generator and guide');
ai_check(str_contains($ai,'[$ok,$text]=ai_converse_request($messages,$c);'),'task generation supports AWS Converse / IAM configurations');
ai_check(str_contains($ai,'$effective=$uid>0?ai_credentials($uid):$g;')&&str_contains($ai,"'configured'=>\$configured"),'user AI status reflects the effective user-or-admin connection');

foreach(['rename','delete','pin','move','project-create','project-save','project-view','model-preference'] as $action)ai_check(str_contains($api,"'".$action."'"),'AI API supports '.$action);
foreach(['ai_user_preferences','ai_project_settings','project_id BIGINT UNSIGNED NULL','model_id VARCHAR(190) NULL','is_pinned TINYINT(1)'] as $definition)ai_check(str_contains($schema,$definition),'schema includes '.$definition);
foreach(['inspect_chat_attachment','extract_chat_zip','read_extracted_file','save_extracted_file','read_workspace_file','write_workspace_file','delete_workspace_file','package_workspace_files'] as $tool){
    $available=false;
    foreach(ai_agent_tools() as $candidate)if(($candidate['name']??'')===$tool)$available=true;
    ai_check($available,'bounded AI workspace tool is available: '.$tool);
}
ai_check(!ai_zip_member_is_safe('../outside.php')&&!ai_zip_member_is_safe('/absolute.php')&&!ai_zip_member_is_safe('src/*.php')&&ai_zip_member_is_safe('src/app.php'),'ZIP entry validation rejects traversal, absolute paths, and wildcard matches');
ai_check(ai_safe_workspace_file('src/app.php')&&!ai_safe_workspace_file('../app.php')&&!ai_safe_workspace_file('-option.php'),'private workspace paths reject traversal and option-like paths');

$tempRoot=sys_get_temp_dir().'/pocketcat-ai-zip-'.bin2hex(random_bytes(6));
mkdir($tempRoot,0700,true);
try{
    mkdir($tempRoot.'/source',0700,true);
    file_put_contents($tempRoot.'/source/README.md','sample source file');
    $archive=$tempRoot.'/sample.zip';
    $process=proc_open(['zip','-q','-r',$archive,'source'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$tempRoot);
    if(!is_resource($process))throw new RuntimeException('Unable to run ZIP regression fixture.');
    fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $status=proc_close($process);
    if($status!==0)throw new RuntimeException('Unable to create ZIP regression fixture.');
    $entries=ai_zip_members($archive);
    ai_check(in_array('source/README.md',$entries,true),'ZIP listing reads entry names from a real archive');
    ai_check(ai_zip_read_member($archive,'source/README.md',1024)==='sample source file','ZIP inspection reads entry content with a byte limit');
}finally{
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry){
        $entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname());
    }
    rmdir($tempRoot);
}

echo "AI workspace checks passed ({$checks} assertions).\n";
