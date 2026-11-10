<?php

if (! function_exists('role_home')) {
    function role_home(?string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'petugas'       => '/petugas',
            'kepala_sekolah'=> '/kepala',
            'peminjam'      => '/peminjam',
            default         => '/login',
        };
    }
}

if (! function_exists('role_label')) {
    function role_label(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'petugas'       => 'Pengurus Barang',
            'kepala_sekolah'=> 'Kepala Sekolah',
            'peminjam'      => 'Peminjam',
            default         => $role,
        };
    }
}

if (! function_exists('role_badge')) {
    function role_badge(string $role): string
    {
        return match ($role) {
            'admin' => 'blue',
            'petugas'       => 'amber',
            'kepala_sekolah'=> 'green',
            'peminjam'      => 'blue',
            default         => 'amber',
        };
    }
}

if (! function_exists('item_status_badge')) {
    function item_status_badge(string $status): string
    {
        return match ($status) {
            'disabled'   => 'red',
            'available'  => 'green',
            'borrowed'   => 'amber',
            'damaged'    => 'red',
            'in_handling'=> 'blue',
            'incomplete' => 'amber',
            default      => 'amber',
        };
    }
}

if (! function_exists('item_status_label')) {
    function item_status_label(string $status): string
    {
        return match ($status) {
            'disabled'   => 'Diarsipkan',
            'available'  => 'Tersedia',
            'borrowed'   => 'Dipinjam',
            'damaged'    => 'Rusak',
            'in_handling'=> 'Dalam Penanganan',
            'incomplete' => 'Menunggu Kelengkapan',
            default      => ucfirst($status),
        };
    }
}

if (! function_exists('condition_label')) {
    function condition_label(string $condition): string
    {
        return match ($condition) {
            'good'         => 'Baik',
            'minor_damage' => 'Rusak ringan',
            'major_damage' => 'Rusak berat',
            default        => ucfirst($condition),
        };
    }
}

if (! function_exists('loan_status_label')) {
    function loan_status_label(?string $returnedAt, ?string $dueAt, bool $badge = false): string
    {
        if ($returnedAt !== null) {
            return $badge ? 'green' : 'Selesai';
        }
        if ($dueAt !== null && strtotime($dueAt) < time()) {
            return $badge ? 'red' : 'Terlambat';
        }
        return $badge ? 'amber' : 'Dipinjam';
    }
}

if (! function_exists('damage_status_label')) {
    function damage_status_label(string $status): string
    {
        return match ($status) {
            'reported'     => 'Dilaporkan',
            'in_handling'  => 'Dalam Penanganan',
            'completed'    => 'Selesai',
            'unrepairable' => 'Tidak Dapat Diperbaiki',
            default        => ucfirst($status),
        };
    }
}

if (! function_exists('damage_status_badge')) {
    function damage_status_badge(string $status): string
    {
        return match ($status) {
            'reported'     => 'red',
            'in_handling'  => 'blue',
            'completed'    => 'green',
            'unrepairable' => 'amber',
            default        => 'amber',
        };
    }
}

if (! function_exists('tgl_id')) {
    function tgl_id(?string $date, bool $withTime = false): string
    {
        if (! $date) {
            return "\u{2014}";
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        $fmt = $withTime ? 'd M Y, H:i' : 'd M Y';
        $out = date($fmt, $ts);
        $map = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'];
        return strtr($out, $map);
    }
}

if (! function_exists('tgl_id_panjang')) {
    function tgl_id_panjang(?string $date): string
    {
        if (! $date) {
            return "\u{2014}";
        }
        $ts = strtotime($date);
        $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $hari[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            if ($p !== '') {
                $out .= mb_substr($p, 0, 1);
            }
        }
        return strtoupper($out ?: '?');
    }
}

if (! function_exists('app_setting')) {
    function app_setting(string $key, string $default = ''): string
    {
        static $settings = null;
        if ($settings === null) {
            $settings = [];
            try {
                $db = \Config\Database::connect();
                $rows = $db->table('settings')->select('setting_key, setting_value')->get()->getResult();
                foreach ($rows as $r) {
                    $settings[$r->setting_key] = $r->setting_value;
                }
            } catch (\Throwable $e) {
                $settings = [];
            }
        }
        return $settings[$key] ?? $default;
    }
}

if (! function_exists('school_name')) {
    function school_name(): string
    {
        return app_setting('school_name', 'SMP NEGERI 1 TANJUNGPINANG');
    }
}

if (! function_exists('old_value')) {
    function old_value(string $key, string $default = ''): string
    {
        $old = session()->getFlashdata('old') ?? [];
        return isset($old[$key]) ? (string) $old[$key] : $default;
    }
}

if (! function_exists('has_error')) {
    function has_error(string $field): bool
    {
        $errors = session()->getFlashdata('errors') ?? [];
        return isset($errors[$field]);
    }
}

if (! function_exists('error_text')) {
    function error_text(string $field): string
    {
        $errors = session()->getFlashdata('errors') ?? [];
        return $errors[$field] ?? '';
    }
}

if (! function_exists('flash_alert')) {
    function flash_alert(string $type, string $message): void
    {
        session()->setFlashdata('flash_' . $type, $message);
    }
}
if (! function_exists('upload_photo')) {
    /**
     * Validasi, resize, dan simpan foto sebagai WebP.
     */
    function upload_photo(?CodeIgniter\HTTP\Files\UploadedFile $file, string $dir = 'uploads', string $filenamePrefix = ''): ?string
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('Foto gagal diunggah. Coba ambil ulang.');
        }
        if ($file->getSize() > 12 * 1024 * 1024) {
            throw new RuntimeException('Ukuran foto maksimal 12 MB.');
        }

        $info = @getimagesize($file->getTempName());
        $mime = strtolower((string) ($info['mime'] ?? ''));
        if (! $info || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new RuntimeException('Format foto harus JPG, PNG, WebP, atau AVIF.');
        }
        if ((int) $info[0] * (int) $info[1] > 40_000_000) {
            throw new RuntimeException('Resolusi foto terlalu besar. Maksimal 40 megapiksel.');
        }

        $bytes = file_get_contents($file->getTempName());
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (! $image) {
            throw new RuntimeException('Foto tidak dapat diproses.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) ((@exif_read_data($file->getTempName()) ?: [])['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3       => imagerotate($image, 180, 0),
                6       => imagerotate($image, -90, 0),
                8       => imagerotate($image, 90, 0),
                default => false,
            };
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $directory = WRITEPATH . trim($dir, '/\\');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            imagedestroy($image);
            throw new RuntimeException('Folder penyimpanan foto tidak tersedia.');
        }

        $safePrefix = trim(substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $filenamePrefix), 0, 100), '-_');
        $name = ($safePrefix !== '' ? $safePrefix . '-' : '') . bin2hex(random_bytes(16)) . '.webp';
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if ($mime === 'image/webp' && max($width, $height) <= 1800) {
            imagedestroy($image);
            if (! copy($file->getTempName(), $path)) {
                throw new RuntimeException('Foto gagal disimpan.');
            }
            return trim($dir, '/\\') . '/' . $name;
        }

        $scale = min(1, 1800 / max($width, $height));
        $output = $image;
        if ($scale < 1) {
            $output = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagefill($output, 0, 0, imagecolorallocatealpha($output, 255, 255, 255, 127));
            imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        }

        $saved = imagewebp($output, $path, 82);
        imagedestroy($output);
        if ($output !== $image) {
            imagedestroy($image);
        }
        if (! $saved) {
            throw new RuntimeException('Foto gagal dikompresi.');
        }

        return trim($dir, '/\\') . '/' . $name;
    }
}

if (! function_exists('stored_photo_file')) {
    function stored_photo_file(string $filename): ?string
    {
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,230}\.(?:webp|png|jpe?g|avif|gif)\z/i', $filename)) {
            return null;
        }
        foreach ([WRITEPATH . 'uploads', FCPATH . 'uploads'] as $directory) {
            $file = $directory . DIRECTORY_SEPARATOR . $filename;
            if (! is_link($file) && is_file($file)) {
                return $file;
            }
        }
        return null;
    }
}

if (! function_exists('delete_photo')) {
    function delete_photo(?string $path): void
    {
        $path = str_replace('\\', '/', (string) $path);
        if (str_starts_with($path, 'uploads/') && ($file = stored_photo_file(substr($path, 8)))) {
            unlink($file);
        }
    }
}

if (! function_exists('donut_stops')) {
    function donut_stops(int $total, int $first, int $second): array
    {
        if ($total <= 0) {
            return [0.0, 0.0];
        }

        $firstEnd = min(100, max(0, ($first / $total) * 100));
        $secondEnd = min(100, max($firstEnd, (($first + $second) / $total) * 100));

        return [round($firstEnd, 2), round($secondEnd, 2)];
    }
}


if (! function_exists('ui_icon')) {
    /**
     * Ikon SVG inline untuk navigasi (satu sumber, konsisten lintas platform).
     * Gaya mengikuti bottom nav Petugas: stroke currentColor, viewBox 0 0 20 20.
     */
    function ui_icon(string $name, string $class = 'ui-ico'): string
    {
        static $paths = [
            'home' => '<path d="M3 9.5 10 3l7 6.5"/><path d="M5 8.5V17h10V8.5M8 17v-5h4v5"/>',
            'box' => '<rect x="3.5" y="3.5" width="13" height="13" rx="1.5"/><path d="M7 7h6v6H7z"/>',
            'laptop' => '<rect x="4.5" y="4" width="11" height="9.5" rx="1.4"/><path d="M4.5 13.5 2.8 16h14.4l-1.7-2.5"/>',
            'screen' => '<rect x="3" y="4" width="14" height="10" rx="1.5"/><path d="M10 14v2.5m-3 0h6"/>',
            'book' => '<path d="M10 5.5C7.5 4 5 4 3 5v10c2-1 4.5-1 7 .5 2.5-1.5 5-1.5 7-.5V5c-2-1-4.5-1-7 .5Zm0 0v10"/>',
            'ball' => '<circle cx="10" cy="10" r="6.5"/><path d="M3.5 10h13M10 3.5v13m-4-11a7 7 0 0 1 0 9m8-9a7 7 0 0 0 0 9"/>',
            'archive' => '<rect x="2.5" y="3" width="15" height="4" rx="1"/><path d="M4 7v9a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7M8 10.5h4"/>',
            'loan' => '<path d="M5 15 15 5M9 5h6v6"/>',
            'clock' => '<circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/>',
            'warning' => '<path d="M10 3.4 17.6 16.6H2.4z"/><path d="M10 8.4v3.4"/><path d="M10 14.4h.01"/>',
            'check' => '<path d="M4 10.6 8.4 15 16 5.4"/>',
            'reports' => '<path d="M3 16.5h14"/><path d="M5.5 16.5V9M10 16.5V3.8M14.5 16.5v-5.5"/>',
            'users' => '<circle cx="7.3" cy="7.6" r="2.6"/><path d="M2.8 16c0-2.6 2-4.2 4.5-4.2S11.8 13.4 11.8 16"/><path d="M12.8 5.4a2.5 2.5 0 0 1 0 4.8"/><path d="M13.4 11.9c2 .3 3.4 1.7 3.4 4.1"/>',
            'teacher' => '<path d="M10 4 3.4 7 10 10l6.6-3z"/><path d="M6 8.6v3c0 1.3 1.8 2.3 4 2.3s4-1 4-2.3v-3"/><path d="M16.6 7v3.4"/>',
            'student' => '<circle cx="10" cy="7" r="3"/><path d="M4 16.6c0-3 2.7-4.6 6-4.6s6 1.6 6 4.6"/>',
            'profile' => '<circle cx="10" cy="10" r="2.6"/><path d="M10 2.6v2.1M10 15.3v2.1M2.6 10h2.1M15.3 10h2.1M4.8 4.8l1.5 1.5M13.7 13.7l1.5 1.5M15.2 4.8l-1.5 1.5M6.3 13.7l-1.5 1.5"/>',
            'logout' => '<path d="M12 6.2V4H4v12h8v-2.2"/><path d="M8.5 10h8.5M14 7l3 3-3 3"/>',
            'key' => '<circle cx="7" cy="10" r="3.2"/><path d="M10.2 10H17M14 10v2M16 10v2"/>',
            'plus' => '<path d="M10 4v12M4 10h12"/>',
            'dots' => '<circle cx="4.2" cy="10" r="1.3" fill="currentColor" stroke="none"/><circle cx="10" cy="10" r="1.3" fill="currentColor" stroke="none"/><circle cx="15.8" cy="10" r="1.3" fill="currentColor" stroke="none"/>',
            'calendar' => '<rect x="3.4" y="4.4" width="13.2" height="12.2" rx="1.6"/><path d="M3.4 8h13.2M7 3v3M13 3v3"/>',
            'eye' => '<path d="M1.6 10S4.7 4.6 10 4.6 18.4 10 18.4 10 15.3 15.4 10 15.4 1.6 10 1.6 10Z"/><circle cx="10" cy="10" r="2.4"/>',
            'eye-off' => '<path d="M2 10s3.1-5.4 8-5.4c1.5 0 2.8.5 3.9 1.1M17.4 8.2c.6.8 1 1.8 1 1.8s-3.1 5.4-8.4 5.4c-1.4 0-2.6-.4-3.7-1"/><path d="M8 8.2a2.4 2.4 0 0 0 3.5 3.3"/><path d="M3 3l14 14"/>',
            'inbox' => '<path d="M3 12l2.5-6.5h9L17 12"/><path d="M3 12v4.5h14V12h-4a3 3 0 0 1-6 0H3Z"/>',
            'search' => '<circle cx="8.7" cy="8.7" r="5.2"/><path d="m12.6 12.6 4 4"/>',
            'filter' => '<path d="M3 5h14M5 10h10M8 15h4"/>',
            'scan' => '<path d="M7 3H3v4M13 3h4v4M17 13v4h-4M7 17H3v-4"/><rect x="7" y="7" width="6" height="6" rx=".8"/>',
        ];

        $svg = $paths[$name] ?? '';
        if ($svg === '') {
            return '';
        }

        return '<svg class="' . esc($class, 'attr') . '" viewBox="0 0 20 20" aria-hidden="true" focusable="false">' . $svg . '</svg>';
    }
}

if (! function_exists('per_page')) {
    /**
     * Baris per halaman terpilih dari query ?per_page, dibatasi ke opsi valid.
     */
    function per_page(int $default = 10): int
    {
        $allowed = [10, 50, 100, 1000];
        $value = (int) (service('request')->getGet('per_page') ?? 0);

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
