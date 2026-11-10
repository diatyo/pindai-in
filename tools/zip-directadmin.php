<?php

declare(strict_types=1);

// Explicit Unix metadata is required: Windows ZIP defaults can make directories 0644.
$source = realpath($argv[1] ?? '') ?: throw new RuntimeException('Folder payload tidak ditemukan.');
$target = $argv[2] ?? throw new RuntimeException('Path ZIP diperlukan.');
if (file_exists($target)) {
    throw new RuntimeException('ZIP tujuan sudah ada; gunakan nama baru.');
}

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Tidak dapat membuat ZIP.');
}
try {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($entries as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Symlink tidak diizinkan dalam payload.');
        }
        $name = str_replace('\\', '/', substr($entry->getPathname(), strlen($source) + 1));
        if ($entry->isDir()) {
            $name .= '/';
            $added = $zip->addEmptyDir($name);
            $attributes = (0040755 << 16) | 0x10;
        } else {
            $added = $zip->addFile($entry->getPathname(), $name);
            $attributes = 0100644 << 16;
        }
        if (! $added || ! $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $attributes)) {
            throw new RuntimeException('Gagal menulis metadata: ' . $name);
        }
    }
} finally {
    if (! $zip->close()) {
        throw new RuntimeException('Gagal menyimpan ZIP.');
    }
}

if ($zip->open($target, ZipArchive::RDONLY) !== true) {
    throw new RuntimeException('Tidak dapat memverifikasi ZIP.');
}
$directories = $files = 0;
try {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $isDirectory = str_ends_with($name, '/');
        $expected = $isDirectory ? 0040755 : 0100644;
        if (! $zip->getExternalAttributesIndex($i, $system, $attributes)
            || $system !== ZipArchive::OPSYS_UNIX
            || (($attributes >> 16) & 0xFFFF) !== $expected
            || str_contains($name, '\\')) {
            throw new RuntimeException('Metadata ZIP tidak valid: ' . $name);
        }
        $isDirectory ? $directories++ : $files++;
    }
} finally {
    $zip->close();
}
echo "ZIP Unix OK: $directories directories 0755, $files files 0644\n";
