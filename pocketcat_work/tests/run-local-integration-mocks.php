<?php
declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

function qa_mock_assert(bool $condition,string $message):void{
    if(!$condition)throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

if(PHP_SAPI!=='cli')throw new RuntimeException('Local integration mocks may only run from CLI.');
$pdo=db();
ensure_billing_sandbox_schema();
$pdo->beginTransaction();

try{
    putenv('APP_ENV=production');
    putenv('BILLING_SANDBOX=true');
    try{billing_sandbox_checkout(1,'basic');throw new RuntimeException('Billing sandbox unexpectedly accepted a production environment.');}
    catch(RuntimeException $e){if($e->getMessage()==='Billing sandbox unexpectedly accepted a production environment.')throw $e;qa_mock_assert($e->getMessage()==='Local billing sandbox is disabled.','billing sandbox cannot run in production mode');}
    putenv('APP_ENV=local');
    $admin=(int)$pdo->query("SELECT id FROM users WHERE username='local-admin' AND status='active' LIMIT 1")->fetchColumn();
    if(!$admin)throw new RuntimeException('Active local-admin user is required for the local billing mock.');
    $billingResult=billing_sandbox_checkout($admin,'basic',static function(int $userId,string $plan,string $amount):array{
        qa_mock_assert($userId>0&&$plan==='basic'&&$amount==='3.00','billing sandbox processor receives the expected user, plan, and amount');
        return ['status'=>'authorized','reference'=>'sandbox_'.str_repeat('a',32)];
    });
    $sandboxPlan=$pdo->prepare('SELECT plan FROM users WHERE id=?');$sandboxPlan->execute([$admin]);
    qa_mock_assert($billingResult['plan']==='basic'&&$billingResult['amount']==='3.00'&&$sandboxPlan->fetchColumn()==='basic','mock checkout records a transaction and applies the local plan');
    $transaction=$pdo->prepare('SELECT status,provider_reference FROM billing_sandbox_transactions WHERE user_id=? ORDER BY id DESC LIMIT 1');$transaction->execute([$admin]);$transaction=$transaction->fetch();
    qa_mock_assert($transaction['status']==='completed'&&$transaction['provider_reference']===$billingResult['reference'],'sandbox checkout persists its authorized mock transaction');
    $pdo->prepare("UPDATE users SET plan='free' WHERE id=?")->execute([$admin]);
    $pdo->prepare('DELETE FROM billing_sandbox_transactions WHERE user_id=?')->execute([$admin]);

    putenv('APP_ENV=local');
    $providers=[
        'google_drive'=>['access'=>'mock-google-access','refresh'=>'mock-google-refresh','email'=>'mock-google@example.invalid'],
        'onedrive'=>['access'=>'mock-onedrive-access','refresh'=>'mock-onedrive-refresh','email'=>'mock-onedrive@example.invalid'],
        'dropbox'=>['access'=>'mock-dropbox-access','refresh'=>'mock-dropbox-refresh','email'=>'mock-dropbox@example.invalid'],
    ];
    foreach($providers as $provider=>$tokens){
        set_setting('file_'.$provider.'_client_id','local-mock-client-id');
        set_secret_setting('file_'.$provider.'_client_secret','local-mock-client-secret');
    }

    $requests=[];
    $transport=static function(string $method,string $url,array $headers,?string $body)use(&$requests,$providers):array{
        $requests[]=['method'=>$method,'url'=>$url,'headers'=>$headers,'body'=>$body];
        $host=(string)parse_url($url,PHP_URL_HOST);
        $path=(string)parse_url($url,PHP_URL_PATH);
        if($method==='POST'&&str_ends_with($path,'/token')){
            $provider=match($host){
                'oauth2.googleapis.com'=>'google_drive',
                'login.microsoftonline.com'=>'onedrive',
                'api.dropboxapi.com'=>'dropbox',
                default=>throw new RuntimeException('Unexpected mocked OAuth host.'),
            };
            parse_str((string)$body,$form);
            $access=($form['grant_type']??'')==='refresh_token'?'mock-'.$provider.'-refreshed-access':$providers[$provider]['access'];
            return [200,json_encode(['access_token'=>$access,'refresh_token'=>$providers[$provider]['refresh'],'expires_in'=>3600])];
        }
        if($host==='www.googleapis.com'&&str_contains($path,'/drive/v3/about'))return [200,json_encode(['user'=>['emailAddress'=>$providers['google_drive']['email']]])];
        if($host==='graph.microsoft.com'&&$path==='/v1.0/me')return [200,json_encode(['userPrincipalName'=>$providers['onedrive']['email']])];
        if($host==='api.dropboxapi.com'&&$path==='/2/users/get_current_account')return [200,json_encode(['email'=>$providers['dropbox']['email']])];
        if($host==='www.googleapis.com'&&$path==='/drive/v3/files'&&$method==='GET')return [200,'{"files":[]}'];
        if($host==='www.googleapis.com'&&$path==='/drive/v3/files'&&$method==='POST')return [200,'{"id":"mock-google-folder"}'];
        if($host==='www.googleapis.com'&&$path==='/upload/drive/v3/files'&&$method==='POST')return [200,'{"id":"mock-google-file"}'];
        if($host==='www.googleapis.com'&&str_ends_with($path,'/permissions'))return [200,'{}'];
        if($host==='www.googleapis.com'&&str_ends_with($path,'/files/mock-google-file'))return [200,'{"webContentLink":"https://drive.google.invalid/mock-google-file"}'];
        if($host==='graph.microsoft.com'&&str_contains($path,'/drive/root:/')&&$method==='PUT')return [200,'{"id":"mock-onedrive-file","webUrl":"https://onedrive.invalid/mock-onedrive-file"}'];
        if($host==='graph.microsoft.com'&&str_ends_with($path,'/createLink'))return [200,'{"link":{"webUrl":"https://onedrive.invalid/mock-share"}}'];
        if($host==='content.dropboxapi.com'&&$path==='/2/files/upload')return [200,'{"id":"mock-dropbox-file","path_display":"/PocketCat/mock.txt"}'];
        if($host==='api.dropboxapi.com'&&$path==='/2/sharing/create_shared_link_with_settings')return [200,'{"url":"https://dropbox.invalid/mock-share"}'];
        throw new RuntimeException('Unexpected mocked cloud request.');
    };

    foreach($providers as $provider=>$tokens){
        cloud_oauth_callback($provider,'mock-authorization-code',$transport);
        $connection=cloud_connection_status($provider);
        qa_mock_assert($connection['connected']&&$connection['email']===$tokens['email'],$provider.' mocked OAuth callback stores the connection');
    }

    $uploadBody='PocketCat local cloud transport mock.';
    $expected=[
        'google_drive'=>['id'=>'mock-google-file','url'=>'https://drive.google.invalid/mock-google-file'],
        'onedrive'=>['id'=>'mock-onedrive-file','url'=>'https://onedrive.invalid/mock-share'],
        'dropbox'=>['id'=>'mock-dropbox-file','url'=>'https://dropbox.invalid/mock-share'],
    ];
    foreach($providers as $provider=>$tokens){
        $pdo->prepare('UPDATE storage_connections SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE provider=?')->execute([$provider]);
        $requestStart=count($requests);
        $result=cloud_upload_bytes($provider,'PocketCat','mock.txt','text/plain',$uploadBody,$transport);
        qa_mock_assert($result===$expected[$provider],$provider.' mocked upload returns expected provider identifier and share URL');
        $providerRequests=array_slice($requests,$requestStart);
        qa_mock_assert((bool)array_filter($providerRequests,static fn(array $r):bool=>str_contains((string)$r['body'],$uploadBody)),$provider.' mocked upload sends the expected file bytes');
        $expectedAccess=$provider==='dropbox'?$tokens['access']:'mock-'.$provider.'-refreshed-access';
        qa_mock_assert((bool)array_filter($providerRequests,static fn(array $r):bool=>in_array('Authorization: Bearer '.$expectedAccess,$r['headers'],true)),$provider.' mocked upload uses the expected access token');
    }
    qa_mock_assert(count($requests)>=14,'all OAuth, identity, upload, and sharing requests were handled by the local transport stub');

    $admin=$pdo->query("SELECT id FROM users WHERE username='local-admin' AND status='active' LIMIT 1")->fetchColumn();
    if(!$admin)throw new RuntimeException('Active local-admin user is required for the webhook mock.');
    $suffix=bin2hex(random_bytes(8));
    $slug='qa-mock-'.$suffix;
    $pdo->prepare('INSERT INTO short_links(user_id,slug,destination_url,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())')->execute([(int)$admin,$slug,'https://example.invalid/local-qa']);
    $linkId=(int)$pdo->lastInsertId();
    $webhookUrl='https://8.8.8.8/pocketcat-local-qa';
    $webhookSecret='local-qa-webhook-secret-'.$suffix;
    if(!webhook_target_is_safe($webhookUrl))throw new RuntimeException('The public test destination must satisfy the existing webhook safety policy.');
    $pdo->prepare('INSERT INTO link_webhooks(user_id,link_id,url,secret,events_json,active,created_at) VALUES(?,?,?,?,?,1,NOW())')->execute([(int)$admin,$linkId,$webhookUrl,'local-qa-webhook-secret-'.$suffix,'["link.clicked"]']);
    $webhookId=(int)$pdo->lastInsertId();
    $event=['event'=>'link.clicked','link_id'=>$linkId,'slug'=>$slug,'test'=>true];
    $webhookCalls=0;
    link_webhook_event($linkId,$event,static function(string $method,string $url,array $headers,string $payload)use(&$webhookCalls,$webhookUrl,$webhookSecret,$event):array{
        $webhookCalls++;
        qa_mock_assert($method==='POST'&&$url===$webhookUrl,'webhook mock receives the validated HTTPS destination');
        $signature='';
        foreach($headers as $header)if(str_starts_with($header,'X-PocketCat-Signature: '))$signature=substr($header,strlen('X-PocketCat-Signature: '));
        qa_mock_assert(hash_equals(hash_hmac('sha256',$payload,$webhookSecret),$signature),'webhook signature matches the exact JSON payload');
        qa_mock_assert(json_decode($payload,true)===$event,'webhook mock receives the expected event payload');
        return [202,'{"accepted":true}',"HTTP/1.1 202 Accepted\r\nContent-Type: application/json\r\n"];
    });
    $delivery=$pdo->prepare('SELECT status_code,response_body,request_body,request_headers,response_headers FROM link_webhook_deliveries WHERE webhook_id=? ORDER BY id DESC LIMIT 1');
    $delivery->execute([$webhookId]);$delivery=$delivery->fetch();
    qa_mock_assert($webhookCalls===1&&$delivery!==false,'webhook delivery is recorded from the local mock response');
    qa_mock_assert((int)$delivery['status_code']===202&&$delivery['response_body']==='{"accepted":true}','webhook response status and body are persisted');
    qa_mock_assert(json_decode((string)$delivery['request_body'],true)===$event&&str_contains((string)$delivery['request_headers'],'X-PocketCat-Signature:'),'webhook request payload and signature header are persisted');
    qa_mock_assert(str_contains((string)$delivery['response_headers'],'202 Accepted'),'webhook response headers are persisted');

    $pdo->rollBack();
    echo "PASS: local integration mock database changes rolled back\n";
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
