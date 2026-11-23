<?php
declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim($path, '/');
$protected = [
    '/storage',
    '/includes',
    '/admin/partials',
    '/attached_assets',
    '/.git',
    '/.local',
];
foreach ($protected as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        http_response_code(404);
        exit;
    }
}
if (preg_match('#^/(?:config(?:\.local)?(?:\.example)?\.php|README\.md|\.replit|\.gitignore|\.replitignore|dev-router\.php)$#i', $path)) {
    http_response_code(404);
    exit;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Not found';

