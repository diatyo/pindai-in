<?php
/** Fail closed on files or known sensitive content in a distributable source tree. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath($argv[1] ?? '') ?: throw new RuntimeException('Folder source tidak ditemukan.');
$deny = isset($argv[2]) && is_file($argv[2]) ? json_decode(file_get_contents($argv[2]), true, 32, JSON_THROW_ON_ERROR) : [];
$findings = []; $count = 0;
// Only the reviewed captures made with synthetic data are allowed in docs.
$demoPreviews = [
    'docs/assets/preview-dashboard.png' => 'f13a36ad4ee7660b2eec54772f0d7afb832d684b6297cd4417a6176e52f26f02',
    'docs/assets/preview-loan-mobile.png' => '5df8b51ea16f38fa86db7509cc2e9a38edde2658990133857f7eef85db731a5d',
];
$patterns = [
    'private-key' => '/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----/',
    'github-token' => '/\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b/',
    'aws-key' => '/\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/',
    'openai-token' => '/\bsk-(?:proj-)?[A-Za-z0-9_-]{40,}\b/',
    'personal-path' => '~[A-Za-z]:[/\\\\]+Users[/\\\\]+[A-Za-z0-9_.-]+~i',
];
$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static fn ($file) => ! ($file->isDir() && in_array($file->getFilename(), ['.git', 'vendor', 'build'], true))
));
foreach ($iterator as $file) {
    if (! $file->isFile()) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $count++;
    if ($file->isLink() || preg_match('~(?:^|/)(?:\.env(?:\.[^/]+)?|backups|screenshots|sample-uploads|referensi|bab-v|bab5_screenshots_final)(?:/|$)|(?:^|/)client_secret[^/]*\.json$|\.(?:sql|sqlite3?|db|docx?|pdf|xlsx?|csv|zip|pindai|pem|key)$~i', $relative)) {
        $findings[] = ['file' => $relative, 'rule' => 'private-file'];
        continue;
    }
    if (str_starts_with($relative, 'writable/') && ! preg_match('~^writable/(?:\.htaccess|(?:cache|logs|session|uploads)/index\.html)$~', $relative)) {
        $findings[] = ['file' => $relative, 'rule' => 'runtime-data'];
        continue;
    }
    $extension = strtolower($file->getExtension());
    if (in_array($extension, ['ttf','woff','woff2','ico','png'], true)) {
        if (isset($demoPreviews[$relative]) && hash_file('sha256', $file->getPathname()) === $demoPreviews[$relative]) {
            continue;
        }
        if (! preg_match('~^public/(?:assets/(?:fonts/[^/]+\.(?:ttf|woff2?)|images/letterhead/(?:city|school)\.png|logo\.png)|(?:favicon|Pindaiin)\.ico)$~i', $relative)) {
            $findings[] = ['file' => $relative, 'rule' => 'unreviewed-binary'];
        }
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    if (str_contains(substr($contents, 0, 8192), "\0")) { $findings[] = ['file' => $relative, 'rule' => 'unknown-binary']; continue; }
    foreach ($patterns as $rule => $pattern) if (preg_match($pattern, $contents)) $findings[] = ['file' => $relative, 'rule' => $rule];
    foreach ($deny as $text) {
        if (strlen($text) >= 6 && stripos($contents, $text) !== false) {
            // Never include the matched private value in a public report.
            $findings[] = ['file' => $relative, 'rule' => 'known-private-content'];
            break;
        }
    }
}
echo json_encode(['files_checked' => $count, 'passed' => $findings === [], 'findings' => $findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($findings === [] ? 0 : 1);
