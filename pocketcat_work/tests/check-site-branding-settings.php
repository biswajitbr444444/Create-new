<?php
$settings = file_get_contents(__DIR__.'/../admin/settings.php');
$functions = file_get_contents(__DIR__.'/../includes/functions.php');
$navbar = file_get_contents(__DIR__.'/../includes/navbar.php');
$css = file_get_contents(__DIR__.'/../assets/css/responsive-system.css');
$uploadHelpers = file_get_contents(__DIR__.'/../includes/site-branding-upload.php');
$logoUpload = file_get_contents(__DIR__.'/../admin/site-logo-upload.php');
$checks = [
    'site settings POSTs to the extensionless save route' => strpos($settings, 'id="website-name" method="post" action="/admin/site-settings-save/"') !== false && strpos($settings, 'settings-reset" method="post" action="/admin/site-settings-save/"') !== false && strpos(file_get_contents(__DIR__.'/../.htaccess'), 'RewriteRule ^admin/site-settings-save/?$ admin/site-settings-save.php') !== false && strpos($settings, "'Content-Type':'application/json'") !== false && strpos(file_get_contents(__DIR__.'/../admin/site-logo-upload.php'), 'save_uploaded_site_logo(') !== false,
    'upload clients use extensionless upload routes' => strpos(file_get_contents(__DIR__.'/../media/index.php'), "/api/upload-chunk/") !== false && strpos(file_get_contents(__DIR__.'/../assets/js/file-sharing-v14-upload.js'), "/api/files/?action=") !== false && strpos(file_get_contents(__DIR__.'/../assets/js/file-sharing-v14-upload.js'), "/api/files.php?action=") === false,
    'logo upload accepts PNG/JPG/SVG/WebP' => strpos($uploadHelpers, 'image/svg+xml') !== false && strpos($uploadHelpers, "'webp'") !== false && strpos($uploadHelpers, "'png'") !== false && strpos($uploadHelpers, "'jpg'") !== false,
    'logo upload is persisted through existing settings storage' => strpos($settings, "set_setting('logo_url'") !== false,
    'custom logo preview and reset control exist' => strpos($settings, 'site-logo-preview') !== false && strpos($settings, 'remove_site_logo') !== false,
    'website name is not required and can be blank' => strpos($settings, 'name="app_name"') !== false && strpos($settings, 'name="app_name"') < strpos($settings, 'name="app_url"') && !preg_match('/name="app_name"[^>]*required/', $settings),
    'website URL stays synchronized across settings and runtime config' => strpos($functions, 'function set_app_url(') !== false && strpos($settings, "set_app_url((string)post('app_url'") !== false && strpos(file_get_contents(__DIR__.'/../install/index.php'), "set_app_url((string)post('app_url'") !== false && strpos(file_get_contents(__DIR__.'/../admin/site-settings-save.php'), "set_app_url((string)post('app_url'") !== false,
    'header visibility is persisted and defaulted on' => strpos($settings, "set_setting('show_site_name_header'") !== false && strpos($functions, "'show_site_name_header'=>'1'") !== false,
    'public and authenticated headers share brand rendering' => strpos($functions, '$brand=site_brand_markup();') !== false && strpos($navbar, 'site_brand_markup()') !== false,
    'custom logo replaces the header title and restores text if it fails to load' => strpos($functions, 'alt="" role="presentation"') !== false && strpos($functions, 'if(this.nextElementSibling.nextElementSibling)this.nextElementSibling.nextElementSibling.hidden=false') !== false && strpos($settings, 'this.hidden=true;this.nextElementSibling.hidden=false') !== false && strpos($functions, "if(\$show) \$mark.='<span class=\"brand-title\" hidden>") !== false && strpos($functions, "if(\$show) \$mark.='<span class=\"brand-title\">'") !== false && strpos($css, '.brand-title[hidden]{display:none!important}') !== false,
    'logo writes verify destination readability and safe file permissions' => strpos($uploadHelpers, 'is_writable($dir)') !== false && strpos($uploadHelpers, 'is_readable($target)') !== false && strpos($uploadHelpers, 'chmod($target,0644)') !== false && strpos($logoUpload, 'save_uploaded_site_logo(') !== false,
    'responsive logo uses contain sizing' => strpos($css, 'object-fit:contain') !== false && strpos($css, '.site-logo') !== false,
    'broken-logo fallback remains hidden until the image fails' => strpos($css, '.site-logo[hidden],.site-logo-preview img[hidden],.site-logo-fallback[hidden]{display:none!important}') !== false,
];
$failed = false;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ').$label."\n";
    if (!$ok) $failed = true;
}
if ($failed) exit(1);
echo "All site branding settings checks passed.\n";
