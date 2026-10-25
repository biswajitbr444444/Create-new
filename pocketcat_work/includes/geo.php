<?php
declare(strict_types=1);

/**
 * 100% self-hosted GeoIP service.
 * Runtime lookups never call an HTTP API. GeoLite2-City.mmdb is read locally.
 */
function geo_database_path():string{
    $configured=trim((string)setting('geoip_city_db',''));
    if($configured!==''){
        $configured=str_replace(['\\','..'],['/',''], $configured);
        $candidate=dirname(__DIR__).'/'.ltrim($configured,'/');
        if(is_file($candidate))return $candidate;
    }
    return dirname(__DIR__).'/geoip/GeoLite2-City.mmdb';
}
function geo_database_available():bool{return is_file(geo_database_path()) && is_readable(geo_database_path()) && filesize(geo_database_path())>1024;}
function geo_database_build_date():string{
    $f=geo_database_path();if(!is_file($f))return '';
    $v=@filemtime($f);return $v?gmdate('Y-m-d H:i:s',$v).' UTC':'';
}
function geo_reader():?PocketCatMaxMindReader{
    static $reader=null;static $path='';
    $pathNow=geo_database_path();
    if($reader instanceof PocketCatMaxMindReader && $path===$pathNow)return $reader;
    if(!geo_database_available())return null;
    try{$reader=new PocketCatMaxMindReader($pathNow);$path=$pathNow;return $reader;}catch(Throwable $e){error_log('[PocketCat local GeoIP] '.$e->getMessage());$reader=null;return null;}
}
function geo_local_lookup(string $ip):?array{
    if(!filter_var($ip,FILTER_VALIDATE_IP))return null;
    static $cache=[];$key=$ip;
    if(isset($cache[$key]))return $cache[$key];
    if(function_exists('apcu_fetch')){$ok=false;$v=apcu_fetch('pc_geo_'.hash('sha256',$ip),$ok);if($ok&&is_array($v)){return $cache[$key]=$v;}}
    // Prefer the MaxMind C extension when the host provides it; it memory-maps the MMDB and is the fastest path.
    if(class_exists('MaxMind\Db\Reader')){
        static $native=null;static $nativePath='';$path=geo_database_path();
        try{if(!$native || $nativePath!==$path){$native=new \MaxMind\Db\Reader($path);$nativePath=$path;}$raw=$native->get($ip);}catch(Throwable $e){$raw=null;}
    }else{$reader=geo_reader();$raw=$reader?$reader->get($ip):null;}
    if(!is_array($raw))return null;
    try{$country=$raw['country']??[];$registered=$raw['registered_country']??[];$city=$raw['city']??[];$subdiv=$raw['subdivisions'][0]??[];$loc=$raw['location']??[];$postal=$raw['postal']??[];$tz=$loc['time_zone']??'';$out=[
        'ip'=>$ip,
        'country_code'=>strtoupper((string)($country['iso_code']??$registered['iso_code']??'')),
        'country_name'=>(string)($country['names']['en']??$registered['names']['en']??''),
        'region'=>(string)($subdiv['names']['en']??''),
        'city'=>(string)($city['names']['en']??''),
        'postal_code'=>(string)($postal['code']??''),
        'latitude'=>isset($loc['latitude'])?(float)$loc['latitude']:null,
        'longitude'=>isset($loc['longitude'])?(float)$loc['longitude']:null,
        'timezone'=>(string)$tz,
        'isp'=>'','asn'=>'',
        'accuracy_radius'=>isset($loc['accuracy_radius'])?(int)$loc['accuracy_radius']:null,
        'source'=>'GeoLite2-City local'
    ];
    $out['country_name']=substr($out['country_name'],0,120);$out['region']=substr($out['region'],0,120);$out['city']=substr($out['city'],0,120);$out['postal_code']=substr($out['postal_code'],0,30);$out['timezone']=substr($out['timezone'],0,80);
    $cache[$key]=$out;if(function_exists('apcu_store'))apcu_store('pc_geo_'.hash('sha256',$ip),$out,3600);return $out;
    }catch(Throwable $e){error_log('[PocketCat local GeoIP lookup] '.$e->getMessage());return null;}
}
function geo_lookup(string $ip):?array{return geo_local_lookup($ip);}
function geo_status():array{
    $file=geo_database_path();
    $available=is_file($file) && is_readable($file) && (int)@filesize($file)>1024;
    $size=$available?(int)@filesize($file):0;
    $meta=[];$readerError='';$reader=null;
    if($available){
        try{$reader=geo_reader();if($reader){$meta=$reader->metadata();}else{$readerError='The GeoIP database could not be parsed by the local MaxMind reader.';}}
        catch(Throwable $e){$readerError=$e->getMessage();}
    }
    $valid=$available && $reader instanceof PocketCatMaxMindReader && (int)($meta['node_count']??0)>0;
    $desc=$meta['description']['en']??'';
    return ['available'=>$valid,'file_present'=>$available,'valid'=>$valid,'path'=>$file,'size'=>$size,'size_human'=>$size?number_format($size/1048576,1).' MB':'0 MB','modified'=>geo_database_build_date(),'description'=>(string)$desc,'database_type'=>(string)($meta['database_type']??''),'ip_version'=>(int)($meta['ip_version']??0),'record_size'=>(int)($meta['record_size']??0),'node_count'=>(int)($meta['node_count']??0),'reader_error'=>$readerError];
}
