<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

final class SchoolLetterhead
{
    public const TEXT = [
        'letterhead_government' => ['Pemerintah daerah', 'PEMERINTAH KOTA TANJUNGPINANG', 200],
        'letterhead_department' => ['Dinas atau instansi', 'DINAS PENDIDIKAN', 200],
        'letterhead_school_name' => ['Nama sekolah pada kop', 'SEKOLAH MENENGAH PERTAMA NEGERI 1 TANJUNGPINANG', 200],
        'letterhead_accreditation' => ['Akreditasi atau keterangan', '(Akreditasi : A ; SK BAN Sekolah/Madrasah Nomor:1347/BAN-SM/SK/2021)', 500],
        'letterhead_contact' => ['Alamat dan kontak pada kop', 'Jalan Tugupahlawan No.265 Telp. (0771) 21720 Email : smpnegeri1_Tanjungpinang@yahoo.com', 500],
        'letterhead_website' => ['Website dan kode pos pada kop', 'Website : http://smpn1tanjungpinang.sch.id Kode Pos 29121', 300],
    ];
    public const SIZES = [
        'letterhead_government_size' => ['Ukuran tulisan pemerintah (pt)', 14, 9, 18],
        'letterhead_school_size' => ['Ukuran nama sekolah (pt)', 12, 9, 18],
        'letterhead_detail_size' => ['Ukuran keterangan (pt)', 8.5, 7, 12],
        'letterhead_department_size' => ['Ukuran font baris 2 (pt)', 14, 9, 18],
        'letterhead_accreditation_size' => ['Ukuran font baris 4 (pt)', 8.5, 7, 12],
        'letterhead_contact_size' => ['Ukuran font baris 5 (pt)', 8.5, 7, 12],
        'letterhead_website_size' => ['Ukuran font baris 6 (pt)', 9, 7.5, 12.5],
        'letterhead_city_height' => ['Tinggi logo daerah (px)', 88, 40, 120],
        'letterhead_school_height' => ['Tinggi logo sekolah (px)', 98, 40, 120],
    ];
    /** Existing text keys remain stable so saved letterheads do not need a migration. */
    public const LINES = [
        'letterhead_government' => ['letterhead_government_size', 1],
        'letterhead_department' => ['letterhead_department_size', 1],
        'letterhead_school_name' => ['letterhead_school_size', 1],
        'letterhead_accreditation' => ['letterhead_accreditation_size', 0],
        'letterhead_contact' => ['letterhead_contact_size', 0],
        'letterhead_website' => ['letterhead_website_size', 0],
    ];
    public const LOGOS = ['letterhead_city_logo' => 'city.png', 'letterhead_school_logo' => 'school.png'];

    public static function values(?array $settings = null): array
    {
        if ($settings === null) {
            $settings = array_column(\Config\Database::connect()->table('settings')->select('setting_key, setting_value')->get()->getResultArray(), 'setting_value', 'setting_key');
        }
        $values = [];
        foreach (self::TEXT as $key => $field) $values[$key] = (string) ($settings[$key] ?? $field[1]);
        foreach (self::SIZES as $key => $field) {
            $size = is_numeric($settings[$key] ?? null) ? (float) $settings[$key] : $field[1];
            $values[$key] = max($field[2], min($field[3], $size));
        }
        foreach (['letterhead_department_size' => ['letterhead_government_size', 0],
            'letterhead_accreditation_size' => ['letterhead_detail_size', 0],
            'letterhead_contact_size' => ['letterhead_detail_size', 0],
            'letterhead_website_size' => ['letterhead_detail_size', 0.5]] as $key => [$legacyKey, $offset]) {
            if (! array_key_exists($key, $settings)) $values[$key] = $values[$legacyKey] + $offset;
        }
        foreach (self::LINES as $key => [$sizeKey, $defaultBold]) {
            $values[$key . '_bold'] = (string) ($settings[$key . '_bold'] ?? $defaultBold) === '1' ? 1 : 0;
        }
        foreach (self::LOGOS as $key => $default) $values[$key] = (string) ($settings[$key] ?? '');
        return $values;
    }

    public static function logoFile(string $name): ?string
    {
        if (! preg_match('/\Alogo-[a-f0-9]{32}\.png\z/D', $name)) return null;
        return WRITEPATH . 'branding/' . $name;
    }

    public static function logoData(string $key, array $values, ?string $uploadedPng = null): string
    {
        if (! isset(self::LOGOS[$key])) throw new \InvalidArgumentException('Jenis logo tidak dikenal.');
        $path = self::logoFile($values[$key] ?? '');
        $data = $uploadedPng;
        if ($data === null && $path && is_file($path)) $data = @file_get_contents($path) ?: null;
        if ($data === null) {
            $default = FCPATH . 'assets/images/letterhead/' . self::LOGOS[$key];
            $data = is_file($default) ? file_get_contents($default) : '';
        }
        return $data === '' ? '' : 'data:image/png;base64,' . base64_encode($data);
    }

    public static function readUpload(?UploadedFile $file): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) return null;
        if (! $file->isValid() || $file->getSize() > 2097152) {
            throw new \DomainException('Logo gagal diunggah. Gunakan PNG, JPG, atau WebP maksimal 2 MB.');
        }
        $info = @getimagesize($file->getTempName());
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
            || $info[0] > 4096 || $info[1] > 4096) {
            throw new \DomainException('Logo harus berupa PNG, JPG, atau WebP dengan dimensi maksimal 4096 × 4096 piksel.');
        }
        $source = @imagecreatefromstring(file_get_contents($file->getTempName()));
        if (! $source) throw new \DomainException('Berkas logo tidak dapat dibaca sebagai gambar.');
        $scale = min(1, 600 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $side = max($width, $height);
        $image = imagecreatetruecolor($side, $side);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagecopyresampled($image, $source, (int) floor(($side - $width) / 2), (int) floor(($side - $height) / 2), 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        $ok = imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        if (! $ok || ! is_string($png)) throw new \RuntimeException('Logo gagal diproses.');
        return $png;
    }
}
