<?php
declare(strict_types=1);

function billing_sandbox_enabled():bool{
    return strtolower((string)getenv('APP_ENV'))==='local'&&strtolower((string)getenv('BILLING_SANDBOX'))==='true';
}

function ensure_billing_sandbox_schema():void{
    static $ready=false;
    if($ready)return;
    db()->exec("CREATE TABLE IF NOT EXISTS billing_sandbox_transactions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,plan ENUM('basic','premium') NOT NULL,amount DECIMAL(10,2) NOT NULL,currency CHAR(3) NOT NULL DEFAULT 'USD',provider_reference VARCHAR(100) NOT NULL UNIQUE,status ENUM('completed','failed') NOT NULL,created_at DATETIME NOT NULL,INDEX(user_id,created_at),CONSTRAINT fk_billing_sandbox_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready=true;
}

function billing_sandbox_checkout(int $userId,string $plan,?callable $processor=null):array{
    if(!billing_sandbox_enabled())throw new RuntimeException('Local billing sandbox is disabled.');
    if($processor!==null&&PHP_SAPI!=='cli')throw new RuntimeException('Mock billing processors are available only to CLI tests.');
    if($userId<1||!in_array($plan,['basic','premium'],true))throw new InvalidArgumentException('Choose an available paid plan.');
    $catalog=plan_catalog();$meta=$catalog['plan_meta'][$plan]??null;
    if(!is_array($meta)||empty($meta['enabled'])||!is_numeric($meta['price'])||(float)$meta['price']<=0)throw new InvalidArgumentException('The selected plan is not available for sandbox checkout.');
    $amount=number_format((float)$meta['price'],2,'.','');
    $processor??=static fn(int $uid,string $planKey,string $price):array=>['status'=>'authorized','reference'=>'sandbox_'.bin2hex(random_bytes(16))];
    $payment=$processor($userId,$plan,$amount);
    if(!is_array($payment)||($payment['status']??'')!=='authorized'||!is_string($payment['reference']??null)||!preg_match('/^sandbox_[a-f0-9]{32}$/D',$payment['reference']))throw new RuntimeException('Local payment sandbox did not authorize the checkout.');
    ensure_billing_sandbox_schema();
    $pdo=db();$ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction)$pdo->beginTransaction();
    try{
        $user=$pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' FOR UPDATE");$user->execute([$userId]);
        if(!$user->fetchColumn())throw new RuntimeException('An active account is required to complete checkout.');
        $pdo->prepare("INSERT INTO billing_sandbox_transactions(user_id,plan,amount,currency,provider_reference,status,created_at) VALUES(?,?,?,'USD',?,'completed',NOW())")->execute([$userId,$plan,$amount,$payment['reference']]);
        $pdo->prepare('UPDATE users SET plan=?,updated_at=NOW() WHERE id=?')->execute([$plan,$userId]);
        if($ownsTransaction)$pdo->commit();
    }catch(Throwable $e){
        if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
    return ['plan'=>$plan,'amount'=>$amount,'currency'=>'USD','reference'=>$payment['reference']];
}
