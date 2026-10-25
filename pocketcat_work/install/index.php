<?php
require __DIR__.'/../bootstrap.php';

if(is_installed()){
    page_start('Setup complete',false);
    echo '<div class="installer-page"><div class="center-card"><span class="eyebrow">Setup locked</span><h1>PocketCat is ready.</h1><p class="sub">The first-run installer has been permanently locked for this installation.</p><a class="btn full" href="/login/">Go to administrator login</a></div></div></body></html>';
    exit;
}

$install_defaults = is_file(__DIR__.'/defaults.php') ? (require __DIR__.'/defaults.php') : [];
$install_defaults = is_array($install_defaults) ? $install_defaults : [];
$default_db = $install_defaults['db'] ?? [];
$default_admin = $install_defaults['admin'] ?? [];
$default_services = $install_defaults['services'] ?? [];

$_SESSION['install_step']=$_SESSION['install_step']??1;
$step=(int)$_SESSION['install_step'];
$msg='';

function install_pdo(array $c):PDO{
    return new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
}
function install_write_db_config(array $db):void{save_runtime_config($db,'https://pocketcat.online');}

if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST'){
    verify_csrf();
    $action=post('action');

    if($action==='db'){
        $c=[
            'host'=>trim((string)post('host')),
            'port'=>trim((string)post('port','3306')),
            'name'=>trim((string)post('name')),
            'user'=>trim((string)post('user')),
            'password'=>(string)post('password')
        ];
        [$ok,$msg]=test_db($c);
        if($ok){
            $_SESSION['install_db']=$c;
            install_write_db_config($c);
            $_SESSION['install_step']=2;
            redirect('/install/');
        }
    }

    if($action==='build'){
        try{
            $pdo=install_pdo($_SESSION['install_db']);
            $sql=file_get_contents(__DIR__.'/../database/schema.sql');
            if($sql===false) throw new RuntimeException('Schema file could not be read.');

            // MySQL DDL is not transactional, so a failed first attempt can leave a partially
            // created database behind. The installer therefore treats already-applied DDL as
            // idempotent and continues past duplicate table/column/index errors. Any other
            // database error (including missing CREATE/ALTER permission) still stops the build.
            $statements=preg_split('/;\s*(?:\r?\n|$)/',trim($sql));
            $ran=0;$skipped=0;
            foreach($statements as $i=>$q){
                $q=trim($q);
                if($q==='')continue;
                try{
                    $pdo->exec($q);$ran++;
                }catch(PDOException $e){
                    $code=(int)($e->errorInfo[1]??0);
                    // 1050 table exists, 1060 duplicate column, 1061 duplicate key name.
                    if(in_array($code,[1050,1060,1061],true)){$skipped++;continue;}
                    error_log('[PocketCat installer] schema statement '.($i+1).' failed: '.$e->getMessage().' | SQL: '.substr(preg_replace('/\s+/',' ',$q),0,1200));
                    throw new RuntimeException('Schema statement '.($i+1).' failed (MySQL '.$code.').',$code,$e);
                }
            }
            $_SESSION['install_schema_stats']=['ran'=>$ran,'skipped'=>$skipped];
            $_SESSION['install_step']=3;
            redirect('/install/');
        }catch(Throwable $e){
            error_log('[PocketCat installer] schema build failed: '.$e->getMessage());
            $detail='';
            if($e instanceof RuntimeException && $e->getCode()>0)$detail=' (database error '.$e->getCode().')';
            $msg='Database schema could not be created'.$detail.'. Check that the database user has CREATE TABLE and ALTER permissions and that the selected database is writable.';
        }
    }

    if($action==='admin'){
        $name=trim((string)post('name'));$email=trim((string)post('email'));$username=trim((string)post('username'));
        $password=(string)post('password');
        $_SESSION['install_admin_name']=$name;
        $_SESSION['install_admin_email']=$email;
        $_SESSION['install_admin_username']=$username;
        $_SESSION['install_admin_password']=$password;
        $_SESSION['install_admin_password_confirm']=(string)post('confirm');
        if($name===''||!valid_email($email)||$username===''||strlen($password)<10||$password!==post('confirm')){
            $msg='Please provide a valid name, email, username and matching password of at least 10 characters.';
        }else{
            try{
                $pdo=install_pdo($_SESSION['install_db']);
                $s=$pdo->prepare("INSERT INTO users(full_name,email,username,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?, 'admin','active',NOW(),NOW())");
                $s->execute([$name,$email,$username,password_hash($password,PASSWORD_DEFAULT)]);
                $_SESSION['install_admin_email']=$email;
                $_SESSION['install_step']=4;
                redirect('/install/');
            }catch(Throwable $e){$msg='Administrator could not be created. The email or username may already exist.';}
        }
    }

    if($action==='services'){
        $fields=['bedrock_api_key','aws_access_key_id','aws_secret_access_key','aws_session_token','bedrock_relay_token','aws_region','bedrock_model_id','bedrock_api_mode','bedrock_transport','bedrock_relay_url','smtp_host','smtp_port','smtp_username','smtp_password','smtp_from_email','smtp_from_name'];
        foreach($fields as $k){
            $v=trim((string)post($k,''));
            if(in_array($k,['bedrock_api_key','aws_access_key_id','aws_secret_access_key','aws_session_token','smtp_password'],true)){if($v!=='')set_secret_setting($k,$v);continue;}
            set_setting($k,$v);
        }
        set_setting('bedrock_endpoint_url',rtrim(trim((string)post('bedrock_endpoint_url','')),'/')); app_secret();
        set_setting('app_name',trim((string)post('app_name','PocketCat'))?:'PocketCat');
        set_app_url((string)post('app_url','https://pocketcat.online'));
        $_SESSION['install_step']=5;
        redirect('/install/');
    }

    if($action==='finish'){
        $dir=__DIR__.'/../storage';
        if(!is_dir($dir))mkdir($dir,0750,true);
        file_put_contents($dir.'/installed.lock','Installed '.date('c'),LOCK_EX);
        $_SESSION=[];
        redirect('/login/');
    }
}

page_start('First-run setup',false);
?>
<div class="installer-page">
  <div class="installer">
    <div class="install-head">
      <div class="logo">P</div>
      <h1>Set up PocketCat</h1>
      <p>One-time setup for your database, administrator account, email and Amazon Bedrock.</p>
    </div>
    <div class="steps">
      <?php foreach(['Database','Build','Admin','Services','Finish'] as $i=>$label): ?>
        <span class="<?=$step===$i+1?'active':''?>" title="<?=e($label)?>"><?=$i+1?></span>
      <?php endforeach; ?>
    </div>
    <?php if($msg): ?><div class="flash error"><?=e($msg)?></div><?php endif; ?>

    <?php if($step===1): ?>
      <form class="card form-card" method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="db">
        <span class="eyebrow">Step 1 · Database</span><h2>Connect your MySQL database</h2>
        <p class="muted">Create the database in your InfinityFree control panel first, then enter the credentials here. PocketCat does not require root access.</p>
        <div class="checks"><span>PHP <?=PHP_VERSION?> ✓</span><span>PDO <?=extension_loaded('pdo')?'✓':'✗'?></span><span>PDO MySQL <?=extension_loaded('pdo_mysql')?'✓':'✗'?></span><span>cURL <?=extension_loaded('curl')?'✓':'✗'?></span></div>
        <div class="grid2">
          <label>Database Host<input name="host" value="<?=e($_SESSION['install_db']['host']??($default_db['host']??'localhost'))?>" required></label>
          <label>Port<input name="port" value="<?=e($_SESSION['install_db']['port']??($default_db['port']??'3306'))?>" required></label>
          <label>Database Name<input name="name" value="<?=e($_SESSION['install_db']['name']??($default_db['name']??''))?>" required></label>
          <label>Database Username<input name="user" value="<?=e($_SESSION['install_db']['user']??($default_db['user']??''))?>" required></label>
        </div>
        <label>Database Password<input type="password" name="password" value="<?=e($_SESSION['install_db']['password']??($default_db['password']??''))?>" autocomplete="new-password" required></label>
        <button class="btn">Test Connection & Continue</button>
      </form>
    <?php elseif($step===2): ?>
      <form class="card form-card" method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="build">
        <span class="eyebrow">Step 2 · Database</span><h2>Build the PocketCat schema</h2>
        <p class="muted">This creates users, tasks, notes, projects, analytics, contact messages and configuration tables.</p>
        <div class="install-note">The database connection has already been verified. Nothing is exposed to the browser.</div>
        <br><button class="btn">Build Database & Continue</button>
      </form>
    <?php elseif($step===3): ?>
      <form class="card form-card" method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="admin">
        <span class="eyebrow">Step 3 · Administrator</span><h2>Create your admin account</h2>
        <?php if(!empty($_SESSION['install_schema_stats'])): ?><div class="install-note">Schema build completed safely. <?=e((string)($_SESSION['install_schema_stats']['ran']??0))?> statements applied<?=((int)($_SESSION['install_schema_stats']['skipped']??0)>0?' · '.e((string)$_SESSION['install_schema_stats']['skipped']).' existing statements reused':'')?>.</div><?php endif; ?><p class="muted">This account receives access to the full administration menu, including database, SMTP, Bedrock, users, security and site settings.</p>
        <div class="grid2">
          <label>Full Name<input name="name" value="<?=e($_SESSION['install_admin_name']??($default_admin['name']??''))?>" required></label>
          <label>Email<input type="email" name="email" value="<?=e($_SESSION['install_admin_email']??($default_admin['email']??''))?>" required></label>
          <label>Username<input name="username" value="<?=e($_SESSION['install_admin_username']??($default_admin['username']??'admin'))?>" required></label>
        </div>
        <label>Password<input type="password" name="password" value="<?=e($_SESSION['install_admin_password']??($default_admin['password']??''))?>" minlength="10" autocomplete="new-password" required></label>
        <label>Confirm Password<input type="password" name="confirm" value="<?=e($_SESSION['install_admin_password_confirm']??($default_admin['password']??''))?>" minlength="10" autocomplete="new-password" required></label>
        <button class="btn">Create Administrator & Continue</button>
      </form>
    <?php elseif($step===4): ?>
      <form class="card form-card" method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="services">
        <span class="eyebrow">Step 4 · Services</span><h2>Configure email & Amazon Bedrock</h2>
        <p class="muted">Both services are optional. The Bedrock settings are pre-filled for this deployment; SMTP is left blank until a valid mailbox/SMTP service is configured.</p>

        <div class="install-section"><h3>Amazon Bedrock</h3>
          <label>Bedrock API Key <span class="badge">Bearer token</span><input type="password" name="bedrock_api_key" value="<?=e($default_services['bedrock_api_key']??'')?>" placeholder="Paste your Amazon Bedrock API key" autocomplete="new-password"></label><label>Custom Bedrock Endpoint URL <span class="badge">Optional</span><input name="bedrock_endpoint_url" value="<?=e($default_services['bedrock_endpoint_url']??'')?>" placeholder="https://bedrock-runtime.region.amazonaws.com/openai/v1"></label>
          <label>Relay URL <span class="badge">Optional relay</span><input name="bedrock_relay_url" value="<?=e($default_services['bedrock_relay_url']??'')?>" placeholder="https://your-worker.workers.dev"></label>
          <label>Relay Shared Token <span class="badge">Secret</span><input type="password" name="bedrock_relay_token" value="<?=e($default_services['bedrock_relay_token']??'')?>" autocomplete="new-password"></label>
          <div class="grid2">
            <label>Transport<select name="bedrock_transport"><option value="auto" <?=($default_services['bedrock_transport']??'auto')==='auto'?'selected':''?>>Auto (relay if configured)</option><option value="relay" <?=($default_services['bedrock_transport']??'')==='relay'?'selected':''?>>Relay (use if direct AWS HTTPS is blocked)</option><option value="direct" <?=($default_services['bedrock_transport']??'')==='direct'?'selected':''?>>Direct to AWS</option></select></label>
            <label>Region<input name="aws_region" value="<?=e($default_services['aws_region']??'us-east-1')?>"></label>
            <label>Model ID<input name="bedrock_model_id" value="<?=e($default_services['bedrock_model_id']??'openai.gpt-oss-120b')?>"></label>
            <label>API Mode<select name="bedrock_api_mode"><option value="responses" <?=($default_services['bedrock_api_mode']??'responses')==='responses'?'selected':''?>>OpenAI-compatible Responses</option><option value="auto" <?=($default_services['bedrock_api_mode']??'')==='auto'?'selected':''?>>Auto</option><option value="converse" <?=($default_services['bedrock_api_mode']??'')==='converse'?'selected':''?>>Bedrock Converse</option></select></label>
          </div>
          <details class="install-note"><summary>Optional legacy IAM credentials</summary>
            <div class="grid2"><label>Access Key ID<input name="aws_access_key_id" value="<?=e($default_services['aws_access_key_id']??'')?>"></label><label>Secret Access Key<input type="password" name="aws_secret_access_key" value="<?=e($default_services['aws_secret_access_key']??'')?>"></label><label>Session Token<input type="password" name="aws_session_token" value="<?=e($default_services['aws_session_token']??'')?>"></label></div>
          </details>
        </div>

        <div class="install-section"><h3>Email / SMTP</h3>
          <div class="grid2">
            <label>SMTP Host<input name="smtp_host" value="<?=e($default_services['smtp_host']??'')?>" placeholder="mail.yourhost.com"></label>
            <label>SMTP Port<input name="smtp_port" value="<?=e($default_services['smtp_port']??'587')?>"></label>
            <label>SMTP Username<input name="smtp_username" value="<?=e($default_services['smtp_username']??'')?>"></label>
            <label>SMTP Password<input type="password" name="smtp_password" value="<?=e($default_services['smtp_password']??'')?>" autocomplete="new-password"></label>
            <label>From Email<input type="email" name="smtp_from_email" value="<?=e($default_services['smtp_from_email']??'support@pocketcat.online')?>"></label>
            <label>From Name<input name="smtp_from_name" value="<?=e($default_services['smtp_from_name']??'PocketCat')?>"></label>
          </div>
        </div>

        <div class="install-section"><h3>Website</h3>
          <div class="grid2"><label>Website Name<input name="app_name" value="<?=e($default_services['app_name']??'PocketCat')?>"></label><label>Website URL<input name="app_url" value="<?=e($default_services['app_url']??'https://pocketcat.online')?>"></label></div>
        </div>
        <button class="btn">Save Services & Continue</button>
      </form>
    <?php else: ?>
      <form class="card form-card" method="post">
        <?=csrf_field()?><input type="hidden" name="action" value="finish">
        <span class="eyebrow">Step 5 · Ready</span><h2>Your PocketCat workspace is ready.</h2>
        <div class="install-note">✓ Database configured<br>✓ Schema created<br>✓ Administrator created<br>✓ Service settings saved</div>
        <p class="muted">Finishing now writes the installation lock. After this, <strong>/install/</strong> will no longer show the setup wizard.</p>
        <button class="btn">Finish Setup & Lock Installer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
