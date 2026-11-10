<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

if (! function_exists('qr_unit_code')) {
    function qr_unit_code(string $value): string
    {
        return trim((string) preg_replace('/^PINDAI\.IN\s*\|\s*/i', '', trim($value)));
    }
}

if (! function_exists('qr_payload')) {
    function qr_payload(string $itemCode): string
    {
        return 'PINDAI.IN|' . $itemCode;
    }
}

if (! function_exists('qr_builder')) {
    function qr_builder(string $itemCode): Builder
    {
        return new Builder(
            writer: new PngWriter(),
            data: qr_payload($itemCode),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 420,
            margin: 6,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );
    }
}

if (! function_exists('qr_filename')) {
    function qr_filename(string $itemCode): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-_]/', '-', $itemCode);
        return 'qr_' . strtolower($safe) . '.png';
    }
}

if (! function_exists('generate_qr_file')) {
    function generate_qr_file(string $itemCode): string
    {
        $filename = qr_filename($itemCode);
        $dir = FCPATH . 'qr';
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Folder QR tidak dapat dibuat.');
        }
        $path = $dir . DIRECTORY_SEPARATOR;
        $temporary = $path . '.qr-' . bin2hex(random_bytes(12)) . '.tmp';
        try {
            $png = qr_builder($itemCode)->build()->getString();
            if (@file_put_contents($temporary, $png) !== strlen($png) || ! @rename($temporary, $path . $filename)) {
                throw new RuntimeException('File QR tidak dapat disimpan.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
        return $filename;
    }
}

if (! function_exists('qr_data_uri')) {
    function qr_data_uri(string $itemCode): string
    {
        return qr_builder($itemCode)->build()->getDataUri();
    }
}

if (! function_exists('stored_qr_filename')) {
    function stored_qr_filename(string $itemCode, ?string $filename): ?string
    {
        $filename = $filename ?: qr_filename($itemCode);
        $path = FCPATH . 'qr/' . $filename;
        return preg_match('/\A[A-Za-z0-9_-]+\.png\z/i', $filename) && is_file($path) && is_readable($path)
            ? $filename
            : null;
    }
}

if (! function_exists('qr_image_src')) {
    function qr_image_src(string $itemCode, ?string $filename): string
    {
        $file = stored_qr_filename($itemCode, $filename);
        return $file ? base_url('qr/' . $file) : qr_data_uri($itemCode);
    }
}
