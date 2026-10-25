<?php
require __DIR__.'/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$config=demo_growth_config();
$series=demo_growth_series();
json_response([
    'ok'=>true,
    'title'=>$config['title'],
    'description'=>$config['description'],
    'show_badge'=>$config['show_badge'],
    'series'=>$series,
    'updated_at'=>date(DATE_ATOM),
]);
