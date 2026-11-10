<?php
// Test-only router; never deploy. DotEnv reads $_ENV ahead of the process environment.
$overrides = json_decode(getenv('PINDAI_ACCESSORY_TEST_OVERRIDES') ?: '', true);
if (! is_array($overrides) || ! preg_match('/\Apindai_acc_test_[a-f0-9]{12}\z/', $overrides['database.default.database'] ?? '')
    || ! str_starts_with($overrides['app.baseURL'] ?? '', 'http://127.0.0.1:')) {
    http_response_code(503);
    exit('Isolated test configuration required.');
}
foreach ($overrides as $key => $value) { $_ENV[$key] = $_SERVER[$key] = $value; putenv($key . '=' . $value); }
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
if (str_starts_with(strtolower($path), '/uploads/')) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    return require dirname(__DIR__) . '/public/index.php';
}
return require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/rewrite.php';
