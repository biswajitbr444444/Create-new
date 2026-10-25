<?php
function csrf_token():string{if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return $_SESSION['_csrf'];}
function csrf_field():string{return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">';}
function verify_csrf():void{$provided=(string)($_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));if(!hash_equals((string)($_SESSION['_csrf']??''),$provided)){http_response_code(419);exit('Security token expired.');}}

function json_response(array $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
