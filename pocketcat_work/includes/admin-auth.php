<?php
function is_admin_view():bool{return !empty($_SESSION['admin_view']);}
function require_admin():void{require_login();if((current_user()['role']??'')!=='admin'){http_response_code(403);exit('403 Forbidden');}if(!is_admin_view()){redirect('/dashboard/');}}
function require_admin_permission(string $permission):void{require_admin();if(!policy_admin_permission($permission)){http_response_code(403);exit('403 Forbidden');}}
function require_super_admin():void{require_admin();$u=current_user();if(!policy_admin_permission('*')){http_response_code(403);exit('Super Admin permission required.');}}
