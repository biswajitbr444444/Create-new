<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') json_response(['ok'=>false,'error'=>'POST required.'], 405);
verify_csrf();
require_once __DIR__.'/../includes/site-branding-upload.php';
try {
    if (!isset($_FILES['site_logo']) || (int)($_FILES['site_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) json_response(['ok'=>false,'error'=>'Choose a logo file first.'], 422);
    $oldLogo = (string)setting('logo_url', '');
    $newLogo = save_uploaded_site_logo($_FILES['site_logo']);
    set_setting('logo_url', $newLogo);
    if ($oldLogo !== '' && $oldLogo !== $newLogo) {
        $oldPath = site_logo_local_path($oldLogo);
        if ($oldPath && is_file($oldPath)) @unlink($oldPath);
    }
    flash('success', 'Site settings and logo saved.');
    json_response(['ok'=>true,'message'=>'Logo uploaded successfully.']);
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>$e->getMessage() ?: 'Logo upload failed.'], 400);
}
