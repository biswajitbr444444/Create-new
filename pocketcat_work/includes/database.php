<?php
function db():PDO{
    static $p;
    if($p instanceof PDO)return $p;
    $c=app_config()['db'];
    if(!$c['host']||!$c['name']||!$c['user'])throw new RuntimeException('Database is not configured.');
    $p=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $p;
}
function test_db(array $c):array{
    try{
        $p=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $p->query('SELECT 1');
        return [true,'Database connection successful.'];
    }catch(Throwable $e){return [false,'Connection failed. Verify host, database name, username and password in your hosting control panel.'];}
}
