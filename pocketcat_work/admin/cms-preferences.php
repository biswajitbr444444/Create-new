<?php
require __DIR__.'/../bootstrap.php'; require_admin(); ensure_cms_schema();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
verify_csrf();
$pref=(string)post('pref',''); $value=(string)post('value','');
$allowed=['meta_order','meta_collapsed','screen_options'];
if(!in_array($pref,$allowed,true)){http_response_code(400);exit('Invalid preference.');}
if(strlen($value)>20000){http_response_code(413);exit('Preference too large.');}
cms_pref_set((int)current_user()['id'],$pref,$value);
header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
