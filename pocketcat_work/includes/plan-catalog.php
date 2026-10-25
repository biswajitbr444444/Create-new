<?php
declare(strict_types=1);

/** Canonical subscription catalogue: system_settings.policy_engine, shared by UI and APIs. */
function plan_catalog_no_cache_headers(): void {
    if (!headers_sent()) { header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); }
}
function plan_catalog(): array {
    $d=policy_data();
    $defaults=policy_defaults();
    $meta=array_replace_recursive($defaults['plan_meta']??[],$d['plan_meta']??[]);
    $plans=[];
    foreach(['free','basic','premium'] as $key){
        $plans[$key]=array_replace($defaults['plans'][$key]??[], $d['plans'][$key]??[]);
    }
    return ['version'=>1,'updated_at'=>setting('policy_engine_updated_at','') ?? '', 'plan_meta'=>$meta,'plans'=>$plans];
}
function plan_period_label(string $period): string {
    return ['month'=>'/ month','year'=>'/ year','one-time'=>' one time'][$period] ?? '/ month';
}
function plan_human_feature_labels(): array {
    return [
        'advanced_analytics'=>'Advanced analytics','website_tracking'=>'Website tracking','url_api'=>'URL API access','url_webhooks'=>'Webhooks',
        'url_custom_alias'=>'Custom short links','url_password'=>'Password-protected URLs','url_geo_os'=>'Operating-system routing','url_geo_country'=>'Country routing','url_geo_city'=>'City routing','url_deep_link'=>'Deep links','url_pixels'=>'Tracking pixels','url_bulk_csv'=>'Bulk CSV tools','url_heatmap'=>'Click heatmaps',
        'file_password'=>'Password-protected files','file_burn'=>'Burn-after-reading files','file_preview'=>'File previews','file_custom_qr'=>'Custom QR codes','file_direct_download'=>'Direct downloads','file_resumable'=>'Resumable uploads','file_permanent'=>'Permanent file links','file_cdn'=>'CDN delivery','file_folder_upload'=>'Folder uploads','file_zip_downloads'=>'ZIP downloads','file_public_share'=>'Public sharing','file_email_share'=>'Email sharing','file_view_only'=>'View-only links',
        'code_password'=>'Password-protected code','code_burn'=>'Burn-after-reading code','code_export_all'=>'Code export','code_private'=>'Private code snippets','code_api'=>'Code API access','code_priority_worker'=>'Priority code processing',
    ];
}
function plan_quota_label(string $key, $value): ?string {
    $unlimited=(is_numeric($value) && (float)$value===0.0);
    $count=['tasks'=>'tasks','projects'=>'projects','notes'=>'notes','url_monthly_links'=>'URL links / month','api_rpm'=>'API requests / minute','api_rph'=>'API requests / hour','code_max_chars'=>'Code characters','code_max_versions'=>'Code versions','code_history_days'=>'Code history','code_preview_runs_per_hour'=>'Code previews / hour','analytics_retention_days'=>'Analytics retention','url_analytics_days'=>'URL analytics retention'];
    if(isset($count[$key])) return $count[$key].': '.($unlimited && in_array($key,['tasks','projects','notes','url_monthly_links','code_max_chars','code_max_versions','code_history_days','code_preview_runs_per_hour','url_analytics_days','api_rpm','api_rph'],true) ? 'Unlimited' : number_format((float)$value));
    if($key==='ai_seconds') return 'AI active usage / day: '.($unlimited?'Unlimited':number_format((float)$value/3600,1).' hours');
    if($key==='file_storage_bytes') return 'File storage: '.($unlimited?'Unlimited':number_format((float)$value/1073741824,1).' GB');
    if($key==='file_max_bytes') return 'Max file size: '.($unlimited?'Unlimited':number_format((float)$value/1073741824,1).' GB');
    if($key==='file_max_expiry_hours') return 'File expiry: '.($unlimited?'No expiry':number_format((float)$value/24,0).' days');
    if($key==='code_max_expiry_hours') return 'Code expiry: '.($unlimited?'No expiry':number_format((float)$value/24,0).' days');
    if($key==='url_redirect_mode') return 'Redirect type: '.(string)$value;
    return null;
}
function plan_card_features(array $p): array {
    $items=[];
    foreach(['tasks','projects','notes','ai_seconds','url_monthly_links','url_redirect_mode','url_analytics_days','analytics_retention_days','file_storage_bytes','file_max_bytes','file_max_expiry_hours','api_rpm','api_rph','code_max_chars','code_max_versions','code_history_days','code_max_expiry_hours','code_preview_runs_per_hour'] as $key){
        if(array_key_exists($key,$p)) { $label=plan_quota_label($key,$p[$key]); if($label!==null)$items[]=$label; }
    }
    foreach(plan_human_feature_labels() as $key=>$label) if(!empty($p[$key])) $items[]=$label;
    if(array_key_exists('ads_on_download',$p)) $items[]=$p['ads_on_download']?'Ads on downloads':'Ad-free downloads';
    if(array_key_exists('qr_watermark',$p)) $items[]=$p['qr_watermark']?'QR watermark':'No QR watermark';
    if(array_key_exists('code_watermark',$p)) $items[]=$p['code_watermark']?'Code watermark':'No code watermark';
    $known=array_merge(
        ['tasks','projects','notes','ai_seconds','url_monthly_links','url_redirect_mode','url_analytics_days','analytics_retention_days','file_storage_bytes','file_max_bytes','file_max_expiry_hours','api_rpm','api_rph','code_max_chars','code_max_versions','code_history_days','code_max_expiry_hours','code_preview_runs_per_hour'],
        array_keys(plan_human_feature_labels()),
        ['ads_on_download','qr_watermark','code_watermark']
    );
    foreach($p as $key=>$value){
        if(in_array($key,$known,true)) continue;
        $label=ucwords(str_replace('_',' ',preg_replace('/^(url|file|code)_/','',(string)$key)));
        if(is_bool($value)){if($value)$items[]=$label;}
        elseif(is_numeric($value)) $items[]=$label.': '.number_format((float)$value);
        elseif(is_string($value)&&$value!=='') $items[]=$label.': '.$value;
    }
    return array_values(array_unique($items));
}
function plan_catalog_public_payload(): array {
    $catalog=plan_catalog(); $out=[];
    foreach(['free','basic','premium'] as $key){
        $meta=$catalog['plan_meta'][$key]??[];
        if(empty($meta['enabled'])) continue;
        $period=(string)($meta['period']??'month');
        $out[]=['key'=>$key,'slug'=>$key,'name'=>(string)($meta['name']??ucfirst($key)),'price'=>(string)($meta['price']??'0'),'period'=>$period,'billing_interval'=>$period,'status'=>'active','description'=>(string)($meta['description']??''),'features'=>plan_card_features($catalog['plans'][$key]??[]),'allocations'=>$catalog['plans'][$key]??[]];
    }
    return ['success'=>true,'schema_version'=>1,'updated_at'=>$catalog['updated_at'],'plans'=>$out];
}
