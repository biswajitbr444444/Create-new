<?php
require __DIR__.'/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!current_user() || (current_user()['role'] ?? '') !== 'admin' || !is_admin_view() || !policy_admin_permission('*')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Administrator access required']); exit; }
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if(!in_array($method,['POST','PUT'],true)){header('Allow: POST, PUT');http_response_code(405);echo json_encode(['success'=>false,'error'=>'Use POST or PUT']);exit;}
$providedCsrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['_csrf']??''));
if ($providedCsrf==='' || !hash_equals((string)($_SESSION['_csrf']??''),$providedCsrf)) {
    http_response_code(419); echo json_encode(['success'=>false,'error'=>'Security token expired. Refresh the page and try again.']); exit;
}
$raw=file_get_contents('php://input');
$payload=json_decode((string)$raw,true);
if(!is_array($payload)) { $payload=$_POST; }
try {
    $d=policy_data(); $defaults=policy_defaults();
    $metaInput=$payload['plan_meta']??$payload['meta']??[];
    $plansInput=$payload['plans']??[];
    if (!is_array($metaInput) || !is_array($plansInput)) throw new RuntimeException('Plan metadata and allocations must be objects.');
    foreach (['free','basic','premium'] as $requiredPlan) {
        if (!isset($metaInput[$requiredPlan]) || !is_array($metaInput[$requiredPlan])) throw new RuntimeException('Missing metadata for plan: '.$requiredPlan.'.');
        foreach (['name','price','period','description','enabled'] as $metaField) {
            if (!array_key_exists($metaField,$metaInput[$requiredPlan])) throw new RuntimeException('Missing '.$metaField.' metadata for plan: '.$requiredPlan.'.');
        }
        if (!isset($plansInput[$requiredPlan]) || !is_array($plansInput[$requiredPlan])) throw new RuntimeException('Missing allocations for plan: '.$requiredPlan.'.');
    }
    foreach(['free','basic','premium'] as $key){
        $m=$metaInput[$key]??[];
        if(!$m && isset($payload['plans'][$key]) && is_array($payload['plans'][$key])) { $m=array_intersect_key($payload['plans'][$key],array_flip(['name','price','period','description','enabled'])); }
        if(isset($payload['plans'][$key]['meta']) && is_array($payload['plans'][$key]['meta'])) $m=array_replace($m,$payload['plans'][$key]['meta']);
        if($m){
            $name=trim((string)($m['name']??$d['plan_meta'][$key]['name']));
            $price=trim((string)($m['price']??$d['plan_meta'][$key]['price']));
            $period=(string)($m['period']??$d['plan_meta'][$key]['period']);
            $description=trim((string)($m['description']??$d['plan_meta'][$key]['description']));
            if($name===''||mb_strlen($name)>40) throw new RuntimeException("Invalid plan name for {$key}.");
            if(!preg_match('/^\d{1,7}(\.\d{1,2})?$/',$price)) throw new RuntimeException("Invalid price for {$key}.");
            if(!in_array($period,['month','year','one-time'],true)) throw new RuntimeException("Invalid billing period for {$key}.");
            $enabled=array_key_exists('enabled',$m)?filter_var($m['enabled'],FILTER_VALIDATE_BOOLEAN):(bool)($d['plan_meta'][$key]['enabled']??true);
            if($key==='free' && !$enabled) throw new RuntimeException('The Free plan must remain enabled for new registrations.');
            $d['plan_meta'][$key]=['name'=>$name,'price'=>$price,'period'=>$period,'description'=>mb_substr($description,0,240),'enabled'=>$enabled];
        }
        $values=$plansInput[$key]??[];
        if(isset($values['quotas'])&&is_array($values['quotas']))$values=array_replace($values,$values['quotas']);
        if(isset($values['features'])&&is_array($values['features']))$values=array_replace($values,$values['features']);
        foreach(array_keys($defaults['plans'][$key]) as $feature){
            if(!array_key_exists($feature,$values)) throw new RuntimeException("Missing allocation {$feature} for {$key}.");
            $default=$defaults['plans'][$key][$feature]; $value=$values[$feature];
            if(is_bool($default)) $d['plans'][$key][$feature]=filter_var($value,FILTER_VALIDATE_BOOLEAN);
            elseif(is_int($default)||is_float($default)) { try { $d['plans'][$key][$feature]=policy_validate_numeric_allocation($feature,$value); } catch (InvalidArgumentException $validationError) { throw new RuntimeException($validationError->getMessage()." for {$key}."); } }
            elseif(is_string($default)) { $value=trim((string)$value); if($feature==='url_redirect_mode'&&!in_array($value,['301','302','307','308'],true)) throw new RuntimeException('Invalid redirect mode.'); $d['plans'][$key][$feature]=$value; }
        }
    }
    policy_save($d,(int)current_user()['id'],'plans.api.update');
    $catalog=plan_catalog_public_payload();
    echo json_encode(['success'=>true,'message'=>'Plan settings saved.','updated_at'=>setting('policy_engine_updated_at','') ?? '', 'plans'=>$catalog['plans'],'catalog'=>$catalog],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException|RuntimeException $e) {
    http_response_code(422); echo json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    error_log('[PocketCat plan API] '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'error'=>'Plan settings could not be saved. Please try again.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
