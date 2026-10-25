<?php
$root = dirname(__DIR__, 2);
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$routes = [
    '/' => '/index.php',
    '/login' => '/auth/login.php',
    '/admin' => '/admin/index.php',
    '/admin/ai' => '/admin/ai.php',
    '/admin/toggle' => '/admin/toggle.php',
    '/dashboard' => '/dashboard/index.php',
    '/settings/ai' => '/settings/ai.php',
    '/ai' => '/ai/index.php',
    '/ai/transform-tasks' => '/ai/transform-tasks.php',
];
if (isset($routes[$path])) {
    require $root . $routes[$path];
    return true;
}
$file = $root . $path;
if (is_file($file)) return false;
http_response_code(404);
return true;
