<?php
declare(strict_types=1);
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Location: /admin/settings/');
    exit;
}
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') === false) {
    // Graceful fallback for browsers with JavaScript disabled.
    require __DIR__.'/settings.php';
    exit;
}
require __DIR__.'/../bootstrap.php';
require_admin();
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) json_response(['ok'=>false,'error'=>'Invalid settings request.'], 400);
$_POST = $payload + $_POST;
verify_csrf();
require_once __DIR__.'/../includes/site-branding-upload.php';
$oldLogo = (string)setting('logo_url', '');
try {
    set_setting('app_name', trim((string)post('app_name', '')));
    set_app_url((string)post('app_url', 'https://pocketcat.online'));
    set_setting('show_site_name_header', post('show_site_name_header') === '1' ? '1' : '0');
    set_setting('redirect_brand_transition', post('redirect_brand_transition') === '1' ? '1' : '0');
    if (post('remove_site_logo') === '1') {
        set_setting('logo_url', '');
        $oldPath = site_logo_local_path($oldLogo);
        if ($oldPath && is_file($oldPath)) @unlink($oldPath);
    }
    if (post('has_logo_upload') !== '1') flash('success', 'Site settings saved.');
    json_response(['ok'=>true,'message'=>'Site settings saved.','logo_upload_pending'=>post('has_logo_upload') === '1']);
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>'Could not save site settings. Check the database connection and permissions.'], 500);
}
