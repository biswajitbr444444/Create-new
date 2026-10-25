<?php
require __DIR__.'/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
echo json_encode(plan_catalog_public_payload(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
