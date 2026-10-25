<?php
require __DIR__.'/../bootstrap.php';
require_login();

$error='';
$sourceText='';
$preview=[];
$selectedModel=trim((string)post('model_id',$_GET['model_id']??''));
if($selectedModel==='' )$selectedModel=ai_user_model_preference((int)current_user()['id'])?:ai_credentials((int)current_user()['id'])['model'];

function pc_next_monday(): string {
    $d=new DateTimeImmutable('today');
    $days=((int)$d->format('N')===1)?7:(8-(int)$d->format('N'));
    return $d->modify('+'.$days.' days')->format('Y-m-d');
}

function pc_parse_ai_tasks(string $text): array {
    $text=str_replace(["\r\n","\r"],"\n",trim($text));
    $lines=preg_split('/\n+/', $text) ?: [];
    $tasks=[]; $seen=[];
    foreach($lines as $line){
        $line=trim($line);
        if($line==='') continue;
        // Accept only actual list items. This prevents model reasoning from appearing in the review.
        if(!preg_match('/^(?:[-*•]|\d+[.)])\s+(.+)$/u',$line,$m)) continue;
        $item=trim($m[1]);
        $priority='Medium';
        $dueDate='';
        if(preg_match('/\s*\[(High|Medium|Low)\]\s*$/i',$item,$pm)){
            $priority=ucfirst(strtolower($pm[1]));
            $item=trim(substr($item,0,-strlen($pm[0])));
        }
        if(preg_match('/\s*\[Due:\s*(\d{4}-\d{2}-\d{2})\]\s*$/i',$item,$dm)){
            $dueDate=$dm[1];
            $item=trim(substr($item,0,-strlen($dm[0])));
        }
        $item=preg_replace('/^[-–—]\s*/u','',$item);
        $key=mb_strtolower(preg_replace('/\s+/u',' ',$item));
        if($item==='' || isset($seen[$key])) continue;
        $seen[$key]=true;
        $tasks[]=['title'=>mb_substr($item,0,220),'priority'=>$priority,'due_date'=>$dueDate];
        if(count($tasks)>=20) break;
    }
    return $tasks;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)post('action','preview');
    if($action==='create'){
        $titles=$_POST['task_title']??[];
        $selected=$_POST['selected']??[];
        $selected=array_fill_keys(array_map('intval',is_array($selected)?$selected:[]),true);
        $priorities=$_POST['task_priority']??[];
        $dueDates=$_POST['task_due_date']??[];
        if(!is_array($titles))$titles=[];
        $count=0;
        $stmt=db()->prepare("INSERT INTO tasks(user_id,title,description,status,priority,due_date,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())");
        foreach($titles as $i=>$title){
            if(!isset($selected[(int)$i])) continue;
            $title=trim((string)$title);
            if($title==='')continue;
            $priority=in_array(($priorities[$i]??'Medium'),['Low','Medium','High'],true)?$priorities[$i]:'Medium';
            $due=trim((string)($dueDates[$i]??''));
            if($due!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$due))$due='';
            $stmt->execute([current_user()['id'],mb_substr($title,0,220),'','Todo',$priority,$due!==''?$due:null]);
            $count++;
        }
        if($count>0){
            increment_counter('tasks_created_counter',$count);
            log_event('tasks_created',(int)current_user()['id']);
            flash('success',$count.' task'.($count===1?'':'s').' created successfully.');
            redirect('/tasks/');
        }
        $error='Select at least one task to create.';
    } else {
        $sourceText=trim((string)post('text'));
        if($sourceText==='')$error='Enter some work text first.';
        else{
            $prompt="You are PocketCat's task extraction engine. Convert the user's work text into a concise checklist.\n\nSTRICT OUTPUT RULES:\n- Output ONLY task lines.\n- Every task MUST begin with '- '.\n- Never output explanations, reasoning, introductions, conclusions, headings, or commentary.\n- One actionable task per line.\n- Keep each task concise and specific.\n- Preserve important dates/deadlines from the user's text.\n- If a task is explicitly high/urgent priority, append [High]; otherwise use [Medium] unless clearly low priority.\n- If a due date is known, append [Due: YYYY-MM-DD].\n- Do not invent a date that is not supported by the user's text.\n\nUSER WORK TEXT:\n".$sourceText;
            try{
                if(!ai_valid_model_id($selectedModel))throw new InvalidArgumentException('The selected model ID is invalid.');
                ai_save_user_model_preference((int)current_user()['id'],$selectedModel);
                [$ok,$raw]=ai_chat($prompt,$selectedModel);
            }catch(Throwable $e){$ok=false;$raw=$e instanceof InvalidArgumentException?$e->getMessage():'AI task generation is temporarily unavailable.';}
            if(!$ok)$error=$raw;
            else{
                $preview=pc_parse_ai_tasks($raw);
                // Fallback for a model that ignores the list-only instruction: use non-empty short lines, but never show its reasoning.
                if(!$preview){
                    $fallback=[];
                    foreach(preg_split('/\n+/',trim($raw)) ?: [] as $line){
                        $line=trim($line);
                        $line=preg_replace('/^(?:[-*•]|\d+[.)])\s*/u','',$line);
                        if($line!=='' && mb_strlen($line)<=220 && !preg_match('/^(we need|let\'s|they want|the user|should be|we can|i can|here|that should)/i',$line))$fallback[]=['title'=>$line,'priority'=>'Medium','due_date'=>''];
                    }
                    $preview=array_slice($fallback,0,20);
                }
                if(!$preview)$error='The AI did not return usable task items. Please try again with a shorter work description.';
            }
        }
    }
}

$title='Turn Text Into Tasks';
require __DIR__.'/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">AI Workspace</span><h1>Turn text into tasks</h1><p class="muted">Generate a clean checklist, review it, then create the selected tasks.</p></div></div>
<div class="ai-modebar card">
<details class="ai-model-picker" data-transform-model-picker>
<summary><span>Model</span><strong data-model-current><?=e($selectedModel)?></strong></summary>
<div class="ai-model-menu">
<label class="visually-hidden" for="transform-model-search">Search models</label>
<input id="transform-model-search" type="search" data-model-search placeholder="Search by model name, vendor, or ID…" autocomplete="off">
<div class="ai-custom-model"><label for="transform-custom-model">Custom Model ID</label><div class="actions"><input id="transform-custom-model" data-model-custom type="text" maxlength="190" placeholder="vendor/model-id" spellcheck="false"><button class="btn small" type="button" data-model-use-custom>Use Custom Model ID</button></div></div>
<div data-model-options class="ai-model-options" role="listbox" aria-label="Available models"></div>
</div></details>
</div>
<?php if($error):?><div class="flash error"><?=e($error)?></div><?php endif;?>
<form class="card form-card" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="preview">
<input type="hidden" name="model_id" value="<?=e($selectedModel)?>">
<label>Work text<textarea name="text" rows="9" placeholder="Example: Launch the website next Monday. Make testing and security high priority."><?=e($sourceText)?></textarea></label>
<button class="btn">Generate Preview</button>
</form>
<?php if($preview):?>
<section class="card">
<h2>Review tasks</h2>
<p class="muted">AI reasoning is hidden. Only actionable task items are shown below.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="create">
<div class="task-preview-list">
<?php foreach($preview as $i=>$task):?>
<div class="task-preview-row">
<label class="checkline"><input type="checkbox" name="selected[]" value="<?=$i?>" checked><span></span></label>
<div class="task-preview-fields">
<input type="text" name="task_title[<?=$i?>]" value="<?=e($task['title'])?>" maxlength="220" required>
<div class="grid2">
<label>Priority<select name="task_priority[<?=$i?>]"><option value="Low" <?=$task['priority']==='Low'?'selected':''?>>Low</option><option value="Medium" <?=$task['priority']==='Medium'?'selected':''?>>Medium</option><option value="High" <?=$task['priority']==='High'?'selected':''?>>High</option></select></label>
<label>Due date<input type="date" name="task_due_date[<?=$i?>]" value="<?=e($task['due_date'])?>"></label>
</div>
</div>
</div>
<?php endforeach;?>
</div>
<button class="btn" type="submit">Create Selected Tasks</button>
</form>
</section>
<?php endif;?>
<script defer src="/assets/js/ai-model-catalog.js?v=19.79"></script>
<script defer src="/assets/js/ai-transform-model-picker.js?v=19.79"></script>
<?php require __DIR__.'/../includes/footer.php'; ?>
