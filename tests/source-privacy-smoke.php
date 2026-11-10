<?php
// Exercise the distribution guard with generated fixtures, never operational data.
$root = dirname(__DIR__);
$fixture = $root . '/build/privacy-fixture-' . bin2hex(random_bytes(5));
mkdir($fixture, 0700, true);
$denyFile = $fixture . '-deny.json';
file_put_contents($denyFile, json_encode(['PRIVATE_FIXTURE_VALUE']));
$check = static function (bool $expected) use ($root, $fixture, $denyFile): void {
    $process = proc_open([PHP_BINARY, $root . '/tools/audit-public-source.php', $fixture, $denyFile],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]); $status = proc_close($process);
    $result = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
    if ($result['passed'] !== $expected || (($status === 0) !== $expected) || $error !== '') throw new RuntimeException('Privacy guard result mismatch.');
};
try {
    file_put_contents($fixture . '/README.md', 'Synthetic documentation only.'); $check(true);
    foreach ([
        '.env' => 'password=test',
        'school.csv' => 'synthetic,test',
        'README.md' => 'ghp_' . str_repeat('A', 36),
        'README.md#person' => 'PRIVATE_FIXTURE_VALUE',
        'README.md#path' => 'C:' . '/Users/' . 'ExamplePerson',
    ] as $name => $contents) {
        $path = $fixture . '/' . explode('#', $name)[0];
        file_put_contents($path, $contents); $check(false); unlink($path);
    }
    echo "PASS source privacy guard: clean fixture accepted; environment, CSV, token, known-private value, and personal path rejected.\n";
} finally {
    foreach (glob($fixture . '/*') as $file) if (is_file($file)) unlink($file);
    if (is_file($fixture . '/.env')) unlink($fixture . '/.env');
    rmdir($fixture); unlink($denyFile);
}
