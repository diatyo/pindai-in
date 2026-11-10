<?php
// Router for the bundled loopback-only PHP server. Apache hosting uses .htaccess.
$port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || ! in_array($host, ['127.0.0.1:' . $port, 'localhost:' . $port], true)) {
    http_response_code(403);
    exit('Akses lokal saja.');
}
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path === '/_desktop/health') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['app' => 'Pindai.in', 'instance' => getenv('PINDAI_DESKTOP_INSTANCE')]);
    return;
}
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.~', $path)) {
    http_response_code(404);
    exit;
}
if (preg_match('~^/qr/(qr_[a-z0-9_-]+\.png)$~i', $path, $matches)) {
    $file = rtrim(getenv('PINDAI_DATA_ROOT') ?: '', '/\\') . '/writable/qr/' . $matches[1];
    if (is_file($file)) {
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        return;
    }
}
// Never bypass the Photos controller's authorization for stored images.
if (preg_match('~^/(?:assets|qr)/[a-zA-Z0-9/_ .@-]+\.(?:css|js|png|jpg|jpeg|webp|svg|ico|woff2?|ttf|map)$~i', $path)
    || in_array($path, ['/favicon.ico', '/robots.txt'], true)) {
    $file = realpath(__DIR__ . '/app/public' . $path);
    $public = realpath(__DIR__ . '/app/public');
    if ($file && $public && str_starts_with(strtolower($file), strtolower($public . DIRECTORY_SEPARATOR)) && is_file($file)) {
        return false;
    }
}
if ($path === '/site.webmanifest') {
    header('Content-Type: application/manifest+json');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . '/app/public/site.webmanifest');
    return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/app/public/index.php';
