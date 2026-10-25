<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function create_link_json(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') create_link_json(['ok'=>false,'error'=>'POST is required.'],405);
$sessionToken=(string)($_SESSION['_csrf']??'');
$provided=(string)($_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));
if ($sessionToken==='' || $provided==='' || !hash_equals($sessionToken,$provided)) create_link_json(['ok'=>false,'error'=>'Security token expired. Please refresh the page and try again.'],419);

$u=current_user();
$id=(int)$u['id'];

try {
    // Existing installations may have an older short_links table. Add the columns used by
    // the link engine before the insert so the API remains safe across upgrades.
    $pdo=db();
    $needed=[
        'title'=>"VARCHAR(220) NULL",
        'password_hash'=>"VARCHAR(255) NULL",
        'expires_at'=>"DATETIME NULL",
        'max_clicks'=>"BIGINT UNSIGNED NOT NULL DEFAULT 0",
        'routing_rules'=>"LONGTEXT NULL",
        'deep_link_url'=>"TEXT NULL",
        'web_fallback_url'=>"TEXT NULL",
        'utm_json'=>"TEXT NULL",
        'tracking_pixels'=>"LONGTEXT NULL",
        'og_title'=>"VARCHAR(220) NULL",
        'og_description'=>"TEXT NULL",
        'og_image_url'=>"TEXT NULL",
        'status'=>"ENUM('active','disabled','expired') NOT NULL DEFAULT 'active'",
        'created_at'=>"DATETIME NULL",
        'updated_at'=>"DATETIME NULL"
    ];
    foreach($needed as $col=>$definition){
        try { $check=$pdo->query("SHOW COLUMNS FROM short_links LIKE '".str_replace("'","''",$col)."'")->fetch(); if(!$check) $pdo->exec("ALTER TABLE short_links ADD COLUMN `$col` $definition"); } catch(Throwable $ignored) {}
    }

    [$can,$reason]=policy_user_can_create_link($id);
    if(!$can) throw new RuntimeException($reason);
    $password=trim((string)($_POST['password']??''));
    if($password!=='') policy_assert_link_feature($id,'url_password');
    $deep=trim((string)($_POST['deep_link_url']??''));
    if($deep!=='') policy_assert_link_feature($id,'url_deep_link');

    $dest=normalize_destination((string)($_POST['destination_url']??''));
    $th=threat_check_url($dest);
    if(!$th['ok']) throw new RuntimeException($th['message']);

    $slug=preg_replace('/[^A-Za-z0-9_-]/','',(string)($_POST['slug']??''));
    if($slug!==''&&!policy_allows($id,'url_custom_alias',false)) throw new RuntimeException('Custom aliases are not available on your plan.');
    if($slug!==''&&(strlen($slug)<3||strlen($slug)>64)) throw new RuntimeException('Custom alias must be between 3 and 64 characters.');
    if($slug!==''&&preg_match('/^(admin|api|login|signup|logout|links|link-analytics|qr|r|settings|dashboard|install)$/i',$slug)) throw new RuntimeException('That alias is reserved. Choose another alias.');
    if($slug==='') $slug=link_slug();

    $exp=null; $expInput=trim((string)($_POST['expires_at']??''));
    if($expInput!==''){
        $ts=strtotime($expInput);
        if(!$ts||$ts<=time()) throw new RuntimeException('Expiration must be a valid future date and time.');
        $exp=date('Y-m-d H:i:s',$ts);
    }
    $maxClicks=(int)($_POST['max_clicks']??0);
    if($maxClicks<0) throw new RuntimeException('Max clicks cannot be negative.');

    $rules=['os'=>[],'country'=>[],'city'=>[]];
    foreach(['iOS','Android','Windows','macOS'] as $os){
        $value=trim((string)($_POST['os_'.$os]??''));
        if($value!==''){
            if(!policy_allows($id,'url_geo_os',false)) throw new RuntimeException('OS routing is not available on your plan.');
            $rules['os'][$os]=normalize_destination($value);
        }
    }
    $country=strtoupper(trim((string)($_POST['country_code']??'')));
    if($country!==''&&!preg_match('/^[A-Z]{2}$/',$country)) throw new RuntimeException('Country code must be a valid 2-letter code.');
    $countryUrl=trim((string)($_POST['country_url']??''));
    if($country&&$countryUrl){
        if(!policy_allows($id,'url_geo_country',false)) throw new RuntimeException('Country routing is not available on your plan.');
        $rules['country'][$country]=normalize_destination($countryUrl);
    }
    $utm=[]; foreach(['utm_source','utm_medium','utm_campaign','utm_term','utm_content'] as $k) $utm[$k]=trim((string)($_POST[$k]??''));
    $pixels=[];
    foreach(['meta_pixel','gtm_id','tiktok_pixel','linkedin_tag'] as $k){ $value=trim((string)($_POST[$k]??'')); if($value!=='') $pixels[$k]=$value; }
    if($pixels) policy_assert_link_feature($id,'url_pixels');

    $title=trim((string)($_POST['title']??''));
    $stmt=$pdo->prepare('INSERT INTO short_links(user_id,slug,destination_url,title,password_hash,expires_at,max_clicks,routing_rules,deep_link_url,web_fallback_url,utm_json,tracking_pixels,og_title,og_description,og_image_url,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    try {
        $stmt->execute([$id,$slug,$dest,$title,$password!==''?password_hash($password,PASSWORD_DEFAULT):null,$exp,$maxClicks,json_encode($rules,JSON_UNESCAPED_SLASHES),$deep,trim((string)($_POST['web_fallback_url']??'')),json_encode($utm,JSON_UNESCAPED_SLASHES),json_encode($pixels,JSON_UNESCAPED_SLASHES),trim((string)($_POST['og_title']??'')),trim((string)($_POST['og_description']??'')),trim((string)($_POST['og_image_url']??''))]);
    } catch(PDOException $e) {
        $code=(int)($e->errorInfo[1]??0);
        if($code===1062) throw new RuntimeException('That custom alias is already in use. Please choose another one.');
        throw $e;
    }
    $newId=(int)$pdo->lastInsertId();
    $q=$pdo->prepare('SELECT * FROM short_links WHERE id=? AND user_id=? LIMIT 1'); $q->execute([$newId,$id]); $new=$q->fetch(PDO::FETCH_ASSOC);
    if(!$new) throw new RuntimeException('The link was created but could not be loaded. Please refresh the page.');
    create_link_json(['ok'=>true,'data'=>[
        'id'=>$newId,'slug'=>$slug,'short_url'=>link_public_url($slug),'destination_url'=>$dest,'title'=>$title,
        'expires_at'=>$new['expires_at']??$exp,'max_clicks'=>(int)($new['max_clicks']??$maxClicks),'status'=>$new['status']??'active','created_at'=>$new['created_at']??date('Y-m-d H:i:s')
    ]],201);
} catch(Throwable $e) {
    error_log('PocketCat create-link API: '.$e->getMessage());
    create_link_json(['ok'=>false,'error'=>$e->getMessage()?:'Unable to create link.'],422);
}
